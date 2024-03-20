<?php

namespace App\Command;

use App\Domain\Common\EntityEnums\FieldType;
use App\Domain\Common\EntityEnums\PolicyTypeDataType;
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
use App\Entity\PolicyTypeFilterType;
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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Press CTRL+C (+enter) to exit at any time.');
        $gameSessionId = $input->getOption(self::OPTION_GAME_SESSION_ID);
        while ((false == $rs = ctype_digit($gameSessionId)) ||
            (false === $this->connectionManager->getCachedServerManagerDbConnection()->executeQuery(
                'SHOW DATABASES LIKE :dbName',
                ['dbName' => $this->connectionManager->getGameSessionDbName((int)$gameSessionId)]
            )->fetchOne())) {
            if ($rs) { // meaning that the game session ID is a number but the database does not exist
                $io->error('Game session database with ID '.$gameSessionId.' does not exist');
            }
            $gameSessionId = $io->ask('Please enter a valid game session ID');
        }
        $gameSessionId = (int) $gameSessionId;
        $io->write('Creating a policy plan for game session ID: '.$gameSessionId);
        $choices = [1 => 'Marine Protected Areas'];
        $layerShort = $io->choice('Choose a layer', $choices, current($choices));
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        if (null === $layer = $em->createQueryBuilder()->select('l')->from(Layer::class, 'l')
            ->where('l.layerShort = :layerShort')
            ->setParameter('layerShort', $layerShort)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)) {
            $io->error('Layer not found');
            return Command::FAILURE;
        }
        $layerGeometryName = null;
        if ($layer['layerGeotype'] !== 'raster') {
            $names = collect($this->connectionManager->getCachedGameSessionDbConnection($gameSessionId)
                ->executeQuery(
                    'SELECT JSON_EXTRACT(geometry_data, \'$.NAME\') FROM geometry WHERE geometry_layer_id = :layerId',
                    ['layerId' => $layer['layerId']]
                )->fetchFirstColumn())->map(fn($name) => json_decode($name))->unique()->sort()->toArray();
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $defaultValue = in_array('Friese Front', $names) ? 'Friese Front' : current($names);
            $question = new Question(
                " \e[32mChoose a ".$layer['layerGeotype']."\e[39m [\e[33m$defaultValue\e[39m]:".PHP_EOL.'> ',
                $defaultValue
            );
            $question->setValidator(function ($answer) use ($names, $layer) {
                if (!in_array($answer, $names)) {
                    throw new \RuntimeException('Invalid '.$layer['layerGeotype']);
                }
                return $answer;
            });
            $question->setAutocompleterValues($names);
            $validInput = false;
            while (!$validInput) {
                try {
                    $layerGeometryName = $helper->ask($input, $output, $question);
                    $validInput = true;
                } catch (\RunTimeException $e) {
                    $io->error($e->getMessage());
                }
            }
        }
        // @todo migrate all policy types to the database, then query all and display them here
        $choices = [1 => 'Buffer zone'];
        $policyType = $io->choice('Choose a policy type', $choices, current($choices));
        $policyValue = null;
        while ($policyValue === null) {
            $policyValue = $io->ask("Enter a $policyType value", '40000');
            if (is_numeric($policyValue) && $policyValue >= 0 && $policyValue <= 100000) {
                $policyValue = (int) $policyValue;
            } else {
                $io->error('The value must be a number between 0 and 100000');
            }
        }
        $choices = [1 => 'fleet', 2 => 'Skip, no filter'];
        $policyFilterTypeName = null;
        if (2 !== $choice = self::ioChoice($io, 'Choose a policy filter', $choices, current($choices))) {
            $policyFilterTypeName = $choices[$choice];
        }
        $policyFilterValue = null;
        if ($choice === 1) { // fleet
            $choices = [1 => 'Bottom Trawl', 2 => 'Industrial and Pelagic Trawl', 3 => 'Drift and Fixed Nets'];
            $policyFilterValue = self::ioChoice($io, 'Enter a fleet', $choices, current($choices));
        }
        try {
            $this->createPlan(
                $gameSessionId,
                $layer,
                $layerGeometryName,
                $policyType,
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
        $em->createQueryBuilder()->delete('App:PolicyTypeFilterType', 'ptft')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterLink', 'pfl')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterType', 'pft')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyType', 'pt')->getQuery()->execute();
        $em->flush();
    }

    private function getPolicyType(string $name): PolicyType
    {
        // @todo migrate all policy types to the database
        // define all policy types here
        $policyType = new PolicyType();
        $policyType
            ->setName('buffer')
            ->setDisplayName('Buffer zone')
            ->setDataType(PolicyTypeDataType::Ranged)
            ->setDataConfig(['min' => 0, 'unit_step_size' => 10000, 'max' => 100000]);
        $policyTypes['Buffer zone'] = $policyType;
        return $policyTypes[$name];
    }

    private function getPolicyFilterType(string $name): PolicyFilterType
    {
        // @todo migrate all policy filter types to the database
        // define all policy filter types here
        $policyFilterType = new PolicyFilterType();
        $policyFilterType
            ->setName('fleet')
            ->setFieldType(FieldType::SMALLINT);
        $policyFilterTypes['fleet'] = $policyFilterType;
        return $policyFilterTypes[$name];
    }

    private function processPolicyFilterValue(string $policyFilterType, string $policyFilterValue): mixed
    {
        $policyFilterType = $this->getPolicyFilterType($policyFilterType);
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
        $em->wrapInTransaction(function () use (
            $em,
            $layer,
            $gameCurrentMonth,
            $geometry,
            $policyTypeName,
            $policyValue,
            $policyFilterTypeName,
            $policyFilterValue
        ) {
            $policyType = $this->getPolicyType($policyTypeName);
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
                //->setGeometryType('1,2,3')
                ->setGeometryDeleted(0)
                ->setGeometryMspid(null);
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
            $policyFilterType = $this->getPolicyFilterType($policyFilterTypeName);
            $em->persist($policyFilterType);
            $policyFilter = new PolicyFilter();
            $policyFilter
                ->setType($policyFilterType)
                ->setValue($this->processPolicyFilterValue($policyFilterTypeName, $policyFilterValue));
            $em->persist($policyFilter);
            $policyFilterLink = new PolicyFilterLink();
            $policyFilterLink
                ->setPolicy($policy)
                ->setPolicyFilter($policyFilter);
            // no need to persist, it's cascaded
            $policyTypeFilterType = new PolicyTypeFilterType();
            $policyTypeFilterType
                ->setPolicyType($policyType)
                ->setPolicyFilterType($policyFilterType);
             // no need to persist, it's cascaded
        });
    }
}
