<?php

namespace App\Command;

use App\Domain\Common\EntityEnums\FieldType;
use App\Domain\Services\ConnectionManager;
use App\Entity\Country;
use App\Entity\Geometry;
use App\Entity\Layer;
use App\Entity\Plan;
use App\Entity\PlanLayer;
use App\Entity\PlanPolicy;
use App\Entity\Policy;
use App\Entity\PolicyFilter;
use App\Entity\PolicyFilterLink;
use App\Entity\PolicyFilterType;
use App\Entity\PolicyLayer;
use App\Entity\PolicyType;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatePolicyPlanCommand extends Command
{
    const OPTION_GAME_SESSION_ID = 'game-session-id';

    protected static $defaultName = 'app:create-policy-plan';

    public function __construct(private readonly ConnectionManager $connectionManager)
    {
        parent::__construct();
    }

    public function askBannedFleets(SymfonyStyle $io): int
    {
        $choices = [
            1 => 'Bottom Trawl',
            2 => 'Industrial and Pelagic Trawl',
            4 => 'Drift and Fixed Nets',
            3 => 'Bottom Trawl + Industrial and Pelagic Trawl',
            5 => 'Bottom Trawl + Drift and Fixed Nets',
            6 => 'Industrial and Pelagic Trawl + Drift and Fixed Nets',
            7 => 'Bottom Trawl + Industrial and Pelagic Trawl + Drift and Fixed Nets'
        ];
        return self::ioChoice($io, 'Choose banned fleets', $choices, current($choices));
    }

    /**
     * @param Question $question
     * @param array $names
     * @param QuestionHelper $helper
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param SymfonyStyle $io
     * @return string
     */
    public function askAndValidateName(
        Question $question,
        array $names,
        QuestionHelper $helper,
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io
    ): string {
        $question->setAutocompleterValues($names);
        $validInput = false;
        $layerGeometryName = '';
        while (!$validInput) {
            try {
                $layerGeometryName = $helper->ask($input, $output, $question);
                $validInput = true;
            } catch (\RunTimeException $e) {
                $io->error($e->getMessage());
            }
        }
        return $layerGeometryName;
    }

    /**
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return int
     * @throws Exception
     */
    public function getGameSessionId(InputInterface $input, SymfonyStyle $io): int
    {
        $gameSessionId = $input->getOption(self::OPTION_GAME_SESSION_ID);
        while ((false == $rs = ctype_digit($gameSessionId)) ||
            (false === $this->connectionManager->getCachedServerManagerDbConnection()->executeQuery(
                'SHOW DATABASES LIKE :dbName',
                ['dbName' => $this->connectionManager->getGameSessionDbName((int)$gameSessionId)]
            )->fetchOne())) {
            if ($rs) { // meaning that the game session ID is a number but the database does not exist
                $io->error('Game session database with ID ' . $gameSessionId . ' does not exist');
            }
            $gameSessionId = $io->ask('Please enter a valid game session ID');
        }
        return (int)$gameSessionId;
    }

    public function askPolicyLayerShortName(
        int $gameSessionId,
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ?string $default = null
    ): ?string {
        try {
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        } catch (\Exception $e) {
            $io->error('Could not connect to the database');
            return null;
        }
        $names = collect(
            $em->createQueryBuilder()
                ->select('l.layerShort')
                ->from(Layer::class, 'l')
                ->getQuery()->getSingleColumnResult()
        )->filter()->unique()->sort()->toArray();
        if (empty($names)) {
            return null;
        }
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $defaultValue = in_array($default, $names) ? $default : current($names);
        $question = new Question(
            " \e[32mChoose a layer\e[39m [\e[33m$defaultValue\e[39m]:" . PHP_EOL . '> ',
            $defaultValue
        );
        $question->setValidator(function ($answer) use ($names) {
            if (!in_array($answer, $names)) {
                throw new \RuntimeException('Non-exisiting layer: '.$answer);
            }
            return $answer;
        });
        return $this->askAndValidateName($question, $names, $helper, $input, $output, $io);
    }

    /**
     * @param int $gameSessionId
     * @param mixed $layerShort
     * @return array|null
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    public function getLayer(int $gameSessionId, mixed $layerShort): ?array
    {
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        return $em->createQueryBuilder()->select('l')->from(Layer::class, 'l')
            ->where('l.layerShort = :layerShort')
            ->setParameter('layerShort', $layerShort)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function askPolicyFilterTypeValue(int $choice, SymfonyStyle $io): mixed
    {
        if ($choice === 1) { // fleet
            return $this->askBannedFleets($io);
        }
        throw new \Exception('Invalid choice');
    }

    public function askPolicyFilterTypeName(SymfonyStyle $io, int &$choice): string
    {
        $choices = [1 => 'fleet', 2 => 'Skip, no filter'];
        $policyFilterTypeName = null;
        if (2 !== $choice = self::ioChoice($io, 'Choose a policy filter', $choices, current($choices))) {
            $policyFilterTypeName = $choices[$choice];
        }
        return $policyFilterTypeName;
    }

    public function askPolicyValue(SymfonyStyle $io, string $policyTypeName): mixed
    {
        $policyValue = null;
        while ($policyValue === null) {
            $policyValue = $io->ask("Enter a $policyTypeName value", '40000');
            if (is_numeric($policyValue) && $policyValue >= 0 && $policyValue <= 100000) {
                $policyValue = (int)$policyValue;
            } else {
                $io->error('The value must be a number between 0 and 100000');
            }
        }
        return $policyValue;
    }

    public function askPolicyTypeName(SymfonyStyle $io): string
    {
        // @todo migrate all policy types to the database, then query all and display them here
        $choices = [1 => 'Buffer zone'];
        return $io->choice('Choose a policy type', $choices, current($choices));
    }

    /**
     * @param int $gameSessionId
     * @param mixed $layer
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param SymfonyStyle $io
     * @param string|null $default
     * @return string|null
     * @throws Exception
     */
    public function askLayerGeometryName(
        int $gameSessionId,
        mixed $layer,
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ?string $default = null
    ): ?string {
        $names = collect($this->connectionManager->getCachedGameSessionDbConnection($gameSessionId)
            ->executeQuery(
                'SELECT JSON_EXTRACT(geometry_data, \'$.NAME\') FROM geometry WHERE geometry_layer_id = :layerId',
                ['layerId' => $layer['layerId']]
            )->fetchFirstColumn())->map(fn($name) => json_decode($name))->unique()->sort()->toArray();
        if (empty($names)) {
            return null;
        }
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $defaultValue = in_array($default, $names) ? $default : current($names);
        $question = new Question(
            " \e[32mChoose a " . $layer['layerGeotype'] . "\e[39m [\e[33m$defaultValue\e[39m]:" . PHP_EOL . '> ',
            $defaultValue
        );
        $question->setValidator(function ($answer) use ($names, $layer) {
            if (!in_array($answer, $names)) {
                throw new \RuntimeException('Non-exisiting '.$layer['layerGeotype'].': '.$answer);
            }
            return $answer;
        });
        return $this->askAndValidateName($question, $names, $helper, $input, $output, $io);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a policy plan')
            ->addOption(
                self::OPTION_GAME_SESSION_ID,
                's',
                InputOption::VALUE_REQUIRED,
                'ID of the game session to create the plan for',
                '1'
            );
    }

    /**
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Press CTRL+C (+enter) to exit at any time.');
        $gameSessionId = $this->getGameSessionId($input, $io);
        $io->writeln('Creating a policy plan for game session ID: '.$gameSessionId);
        if (null === $layerShort = $this->askPolicyLayerShortName(
            $gameSessionId,
            $input,
            $output,
            $io,
            'Marine Protected Areas'
        )) {
            $io->error('Could not retrieve layers from the database');
            return Command::FAILURE;
        }
        if (null === $layer = $this->getLayer($gameSessionId, $layerShort)) {
            $io->error("Layer $layerShort not found");
            return Command::FAILURE;
        }
        $layerGeometryName = null;
        $geometryBannedFleets = null;
        if ($layer['layerGeotype'] !== 'raster') {
            if (null === $layerGeometryName = $this->askLayerGeometryName(
                $gameSessionId,
                $layer,
                $input,
                $output,
                $io,
                'Friese Front'
            )) {
                $io->error('No geometry found for the layer: '.$layerShort);
                return Command::FAILURE;
            }
            $geometryBannedFleets = $this->askBannedFleets($io);
        }
        $policyTypeName = $this->askPolicyTypeName($io);
        $policyValue = $this->askPolicyValue($io, $policyTypeName);
        $choice = 0;
        $policyFilterTypeName = $this->askPolicyFilterTypeName($io, $choice);
        $policyFilterValue = $this->askPolicyFilterTypeValue($choice, $io);
        try {
            $this->createPlan(
                $gameSessionId,
                $layer,
                $layerGeometryName,
                $geometryBannedFleets,
                $policyTypeName,
                $policyValue,
                $policyFilterTypeName,
                $policyFilterValue
            );
        } catch (\Exception $e) {
            $io->error('Failed to create plan: '.$e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Plan created successfully.');
        return Command::SUCCESS;
    }

    private static function ioChoice(SymfonyStyle $io, string $question, array $choices, mixed $default): string|int
    {
        $choiceValues = array_flip($choices);
        $choice = $io->choice($question, $choices, $default);
        return $choiceValues[$choice];
    }

    /**
     * @throws \Exception
     */
    private function cleanUpPreviousPlan(EntityManagerInterface $em): void
    {
        $em->createQueryBuilder()->delete('App:Geometry', 'g')->where('g.geometryFID = :fid')
            ->setParameter('fid', 'policy-plan-test')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PlanPolicy', 'pp')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Plan', 'p')->where('p.planName = :planName')
            ->setParameter('planName', 'policy-plan-test')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyLayer', 'pl')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Layer', 'l')->where('l.layerName = :layerName')
            ->setParameter('layerName', 'policy-plan-test')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Policy', 'p')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilter', 'pf')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterLink', 'pfl')->getQuery()->execute();
        $em->flush();
    }

    private function getPolicyTypes(EntityManagerInterface $em): array
    {
        return collect($em->createQueryBuilder()->select('pt')->from(PolicyType::class, 'pt')
            ->getQuery()->getResult())->keyBy(fn(PolicyType $pt) => $pt->getDisplayName())->all();
    }

    private function getPolicyFilterTypes(EntityManagerInterface $em): array
    {
        return collect($em->createQueryBuilder()->select('pft')->from(PolicyFilterType::class, 'pft')
            ->getQuery()->getResult())->keyBy(fn(PolicyFilterType $pft) => $pft->getName())->all();
    }

    private function processPolicyFilterValue(PolicyFilterType $policyFilterType, string $policyFilterValue): mixed
    {
        switch ($policyFilterType->getFieldType()) {
            case FieldType::SMALLINT:
                return (int) $policyFilterValue;
            case FieldType::BOOLEAN:
                return (bool) $policyFilterValue;
            default:
                break; // nothing to do.
        }
        return $policyFilterValue;
    }

    private function convertBannedFleetFlagsToCommaSeparatedString(int $bannedFleetFlags): string
    {
        $result = [];
        if (($bannedFleetFlags & 1) == 1) {
            $result[] = '1';
        }
        if (($bannedFleetFlags & 2) == 2) {
            $result[] = '2';
        }
        if (($bannedFleetFlags & 4) == 4) {
            $result[] = '3';
        }
        if (empty($result)) {
            return '0';
        }
        return implode(',', $result);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws Exception
     * @throws \Exception
     */
    private function createPlan(
        int $gameSessionId,
        array $layer,
        ?string $layerGeometryName,
        ?int $geometryBannedFleets,
        string $policyTypeName,
        mixed $policyValue,
        ?string $policyFilterTypeName,
        mixed $policyFilterValue
    ): void {
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $this->cleanUpPreviousPlan($em);
        $gameCurrentMonth = (int)$em->createQueryBuilder()->select('g.gameCurrentmonth')->from('App:Game', 'g')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
        $geometry = $this->connectionManager->getCachedGameSessionDbConnection($gameSessionId)
            ->executeQuery(
                'SELECT * FROM geometry WHERE JSON_EXTRACT(geometry_data, \'$.NAME\') = :geometryName',
                ['geometryName' => $layerGeometryName]
            )->fetchAssociative();
        if ($geometry === false) {
            throw new \Exception('MPA not found');
        }

        $policyTypes = $this->getPolicyTypes($em);
        if (empty($policyTypes)) {
            throw new \Exception('No policy types found');
        }
        $policyFilterTypes = $this->getPolicyFilterTypes($em);
        if (empty($policyFilterTypes)) {
            throw new \Exception('No policy filter types found');
        }
        $em->wrapInTransaction(function () use (
            $em,
            $policyTypes,
            $policyFilterTypes,
            $layer,
            $gameCurrentMonth,
            $geometry,
            $geometryBannedFleets,
            $policyTypeName,
            $policyValue,
            $policyFilterTypeName,
            $policyFilterValue
        ) {
            $policyType = $policyTypes[$policyTypeName];
            $em->persist($policyType);
            $policy = new Policy();
            $policy
                ->setType($policyType)
                ->setValue($policyValue);
            $em->persist($policy);
            $plan = new Plan();
            $plan
                ->setPlanName('policy-plan-test')
                ->setCountry($em->getReference(Country::class, 1))
                ->setPlanDescription('')
                ->setPlanTime(new \DateTime())
                ->setPlanGametime($gameCurrentMonth === -1 ? -1 : $gameCurrentMonth + 1)
                ->setPlanState('DESIGN')
                ->setPlanLastupdate(time())
                ->setPlanPreviousstate('NONE')
                ->setPlanActive(1)
                ->setPlanConstructionstart(-1)
                ->setPlanType(0)
                ->setPlanEnergyError(0)
                ->setPlanAltersEnergyDistribution(0);
            $em->persist($plan);
            $layerEntity = new Layer();
            $layerEntity
                ->setOriginalLayer($em->getReference(Layer::class, $layer['layerId']))
                ->setLayerActive(1)
                ->setLayerSelectable(1)
                ->setLayerActiveOnStart(0)
                ->setLayerToggleable(1)
                ->setLayerEditable(1)
                ->setLayerName('policy-plan-test')
                ->setLayerGeotype('')->setLayerShort('')->setLayerGroup('')->setLayerTooltip('')
                ->setLayerCategory($layer['layerCategory'])
                ->setLayerSubcategory($layer['layerSubcategory'])
                ->setLayerKpiCategory($layer['layerKpiCategory'])
                ->setLayerType(null)
                ->setLayerDepth(1)
                ->setLayerInfoProperties(null)
                ->setLayerTextInfo('{}')
                ->setLayerStates($layer['layerStates'])
                ->setLayerRaster(null)
                ->setLayerLastupdate(100)
                ->setLayerEditingType(null)
                ->setLayerSpecialEntityType($layer['layerSpecialEntityType'])
                ->setLayerGreen(0)->setLayerMelupdateConstruction(0)->setLayerFilecreationtime(0)->setLayerMedia(null)
                ->setLayerEntityValueMax(null)->setLayerTags(null);
            $layerEntity->getOriginalLayer()->setLayerMelupdate(1);
            $em->persist($layerEntity);
            $geometryEntity = new Geometry();
            $geometryEntity
                ->setLayer($layerEntity)
                ->setOriginalGeometry($em->getReference(Geometry::class, $geometry['geometry_id']))
                ->setGeometryFID('policy-plan-test')
                ->setGeometryGeometry($geometry['geometry_geometry'])
                ->setGeometryData($geometry['geometry_data'])
                ->setCountry($em->getReference(Country::class, 7))
                ->setGeometryActive(1)
                ->setGeometryToSubtractFrom(null)
                ->setGeometryDeleted(0)
                ->setGeometryMspid(null);
            if (null !== $geometryBannedFleets) {
                $geometryEntity
                    ->setGeometryType($this->convertBannedFleetFlagsToCommaSeparatedString($geometryBannedFleets));
            }
            $em->persist($geometryEntity);
            $planLayer = new PlanLayer();
            $planLayer->setLayer($layerEntity)->setPlan($plan)->setPlanLayerState('ACTIVE');
            // no need to persist, it's cascaded
            $planPolicy = new PlanPolicy();
            $planPolicy
                ->setPlan($plan)
                ->setPolicy($policy);
            // no need to persist, it's cascaded
            $policyLayer = new PolicyLayer();
            $policyLayer
                ->setLayer($layerEntity)
                ->setPolicy($policy);
            // no need to persist, it's cascaded
            if ($policyFilterTypeName == null) {
                return;
            }
            $policyFilterType = $policyFilterTypes[$policyFilterTypeName];
            $em->persist($policyFilterType);
            $policyFilter = new PolicyFilter();
            $policyFilter
                ->setType($policyFilterType)
                ->setValue($this->processPolicyFilterValue($policyFilterType, $policyFilterValue));
            $em->persist($policyFilter);
            $policyFilterLink = new PolicyFilterLink();
            $policyFilterLink
                ->setPolicy($policy)
                ->setPolicyFilter($policyFilter);
             // no need to persist, it's cascaded
        });
    }
}