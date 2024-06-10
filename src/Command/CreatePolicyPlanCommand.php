<?php

namespace App\Command;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanLayerState;
use App\Domain\Common\EntityEnums\PlanState;
use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use App\Domain\Common\EntityEnums\PolicyTypeName;
use App\Domain\PolicyData\FilterBasePolicyData;
use App\Domain\PolicyData\PolicyBasePolicyData;
use App\Domain\PolicyData\PolicyDataFactory;
use App\Domain\PolicyData\PolicyDataSchemaMetaName;
use App\Domain\PolicyData\PolicyTarget;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\Country;
use App\Entity\Game;
use App\Entity\Geometry;
use App\Entity\Layer;
use App\Entity\Plan;
use App\Entity\PlanLayer;
use App\Entity\PlanPolicy;
use App\Entity\Policy;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\JsonSchema;
use Swaggest\JsonSchema\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatePolicyPlanCommand extends Command
{
    const TEST_DATA_PREFIX = 'policy-plan-test';
    const OPTION_GAME_SESSION_ID = 'game-session-id';

    protected static $defaultName = 'app:create-policy-plan';

    private ?EntityManagerInterface $em = null;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        private readonly SymfonyToLegacyHelper $symfonyToLegacyHelper
    ) {
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = [];
        $io = new SymfonyStyle($input, $output);
        $gameSessionId = $this->getGameSessionId($input, $io);
        $context['gameSessionId'] = $gameSessionId;
        $this->em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        if (null === $game = $this->getGame()) {
            $io->error('Could not retrieve game record');
            return Command::FAILURE;
        }
        if (null == $game->getGameStart()) {
            $io->error('Game start year not set');
            return Command::FAILURE;
        }
        $gameCurrentMonth = $game->getGameCurrentmonth();
        // show info to the user
        $io->note(
            'Press CTRL+C (+enter) to exit at any time'.PHP_EOL.
            'Creating a policy plan for game session ID: '.$gameSessionId.PHP_EOL.
            'See datetime format reference: https://www.php.net/manual/en/datetime.format.php'
        );
        $planGameTime = $this->askPlanImplementationTime($io, $game, $gameCurrentMonth);
        $context['planGameTime'] = $planGameTime;
        if (null === $layerShort = $this->askPlanLayerShortName(
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
        $context['layerName'] = $layer->getLayerName();
        $layerGeometryName = null;
        $geometrySupportingLayerTypes = null;
        if ($layer->getLayerGeoType() !== LayerGeoType::RASTER) {
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
            $context = array_merge($context, ['layerGeometryName' => $layerGeometryName]);
            $geometrySupportingLayerTypes = $this->askGeometrySupportingLayerTypes($io, $context);
        }
        // assuming the display name to be unique
        $policyTypeName = $this->askPolicyTypeName($io);
        $policyData = $this->askPolicyData($io, $policyTypeName, $context);
        try {
            $plan = $this->createPlan(
                $gameSessionId,
                $planGameTime,
                $layer,
                $layerGeometryName,
                $geometrySupportingLayerTypes,
                $policyTypeName,
                $policyData,
                PolicyDataFactory::getPolicyDataSchemaByType($policyTypeName)
                    ->getMeta(PolicyDataSchemaMetaName::POLICY_TARGET->value) ?? PolicyTarget::PLAN
            );
        } catch (\Exception $e) {
            $io->error('Failed to create plan: '.$e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Plan created successfully: ' . $plan->getPlanName());
        return Command::SUCCESS;
    }

    private function getGameSessionEntityManager(): EntityManagerInterface
    {
        assert($this->em !== null);
        return $this->em;
    }

    /**
     * @param SymfonyStyle $io
     * @param Game $game
     * @param int|null $gameCurrentMonth
     * @return int
     * @throws \Exception
     */
    public function askPlanImplementationTime(SymfonyStyle $io, Game $game, ?int $gameCurrentMonth): int
    {
        $planImplementationTimeString = $io->ask(
            'Set plan "implementation time" in format Y-m',
            self::addMonthsToYear(
                $game->getGameStart(),
                $gameCurrentMonth === -1 ? 0 : $gameCurrentMonth + 1 // next month
            ),
            fn($s) => (bool)preg_match('/^\d{4}-\d{2}$/', $s) ? $s :
                throw new \RuntimeException('Invalid implementation time format. Use Y-m')
        );
        // Create DateTime objects
        $planImplementationTime = new \DateTime($planImplementationTimeString);
        $startingYear = new \DateTime($game->getGameStart() . "-01-01");

        // Calculate the difference in months
        $interval = $startingYear->diff($planImplementationTime);
        return $interval->y * 12 + $interval->m;
    }

    private function getGame(): ?Game
    {
        static $game = null;
        if ($game !== null) {
            return $game;
        }
        $em = $this->getGameSessionEntityManager();
        try {
            return $em->createQueryBuilder()
                ->select('g')
                ->from('App:Game', 'g')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function addMonthsToYear(int $startingYear, int $monthsToAdd): string
    {
        // Create a DateTime object from the starting year and a default month (January)
        $date = new \DateTime("{$startingYear}-01-01");
        // Add the specified number of months to the date
        $date->modify("+{$monthsToAdd} months");
        return $date->format('Y-m');
    }

    /**
     * @throws \Exception
     */
    private function getDataModel(int $gameSessionId): array
    {
        $game = new \App\Domain\API\v1\Game();
        $game->setGameSessionId($gameSessionId);
        return $game->GetGameConfigValues();
    }

    /**
     * @throws \Exception
     *
     * @return int[]
     */
    public function askGeometrySupportingLayerTypes(SymfonyStyle $io, array $context): array
    {
        if (!array_key_exists('layerName', $context)) {
            throw new \Exception('Layer name not found');
        }
        if (!array_key_exists('gameSessionId', $context)) {
            throw new \Exception('Game session ID not found');
        }
        $dataModel = $this->getDataModel($context['gameSessionId']);
        if (null === $layerConfig = collect($dataModel['meta'])
            ->filter(fn($l) => $l['layer_name'] == $context['layerName'])->first()) {
            throw new \Exception('Layer not found in the game config: '. $context['layerName']);
        }
        if (!isset($layerConfig['layer_type'][0]['displayName'])) {
            throw new \Exception('Layer does not have any required types, or expected field displayName: '.
                $context['layerName']);
        }

        $result = [];
        $choices = collect($layerConfig['layer_type'])->map(fn($l) => $l['displayName'])->toArray();
        while (1) {
            $result[] = self::ioChoice(
                $io,
                'Choose a layer type to add to '.$context['layerGeometryName'],
                $choices,
                current($choices)
            );
            $io->writeln(json_encode($result));
            if (!$io->confirm('Add more layer types to '.$context['layerGeometryName'].'?', false)) {
                break;
            }
        }
        return $result;
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

    public function askPlanLayerShortName(
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
     * @return Layer|null
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    public function getLayer(int $gameSessionId, mixed $layerShort): ?Layer
    {
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        return $em->createQueryBuilder()->select('l')->from(Layer::class, 'l')
            ->where('l.layerShort = :layerShort')
            ->setParameter('layerShort', $layerShort)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
    }

    /**
     * @throws \Exception
     */
    private function askJsonSchemeProperty($io, string $propName, Schema $prop, array $context): mixed
    {
        if (null !== $description = $prop->getMeta(PolicyDataSchemaMetaName::FIELD_ON_INPUT_DESCRIPTION->value)) {
            $io->writeln(PHP_EOL.$description);
        }
        if (null !== $choices = $prop->getMeta(PolicyDataSchemaMetaName::FIELD_ON_INPUT_CHOICES->value)) {
            if (is_callable($choices)) {
                $choices = $choices($context['gameSessionId']);
            }
            if (!is_array($choices) or empty($choices)) {
                $io->warning('No choices available although FIELD_ON_INPUT_CHOICES is set for '.$propName);
                $choices = [];
            }
            foreach ($choices as $key => $choice) {
                $io->writeln($key.': '.$choice);
            }
        }
        $propValue = null;
        switch ($prop->type) {
            case JsonSchema::_ARRAY:
                $propValue = [];
                assert(
                    ($prop->items instanceof Schema) && $prop->items->items === null,
                    'we do not support multidimensional arrays'
                );
                $again = true;
                while ($again) {
                    if (null === $v = $this->askJsonSchemeProperty(
                        $io,
                        $propName,
                        $prop->items,
                        $context
                    )) {
                        $again = false;
                        continue;
                    }
                    $propValue[] = $v;
                    $io->writeln($propName.': '.json_encode($propValue));
                    $again = $io->confirm("Add more to $propName?", false);
                }
                break;
            case JsonSchema::OBJECT:
                $callable = $prop->getMeta(PolicyDataSchemaMetaName::FIELD_OBJECT_SCHEMA_CALLABLE->value);
                if (is_callable($callable)) {
                    $prop = $callable();
                }
                $props = $prop->properties?->toArray() ?? [];
                $v = [];
                foreach ($props as $objPropName => $objProp) {
                    $v[$objPropName] = $this->askJsonSchemeProperty($io, $objPropName, $objProp, $context);
                }
                // special handling : allOf are policy filters
                foreach ($prop->allOf as $filterSchema) {
                    assert(is_subclass_of($filterSchema->getObjectItemClass(), FilterBasePolicyData::class));
                    $props = $filterSchema->getProperties()?->toArray() ?? [];
                    foreach ($props as $objPropName => $objProp) {
                        $v[$objPropName] = $this->askJsonSchemeProperty($io, $objPropName, $objProp, $context);
                    }
                }
                $propValue = new \stdClass();
                foreach ($v as $objPropName => $objProp) {
                    $propValue->$objPropName = $objProp;
                }
                break;
            case JsonSchema::NULL:
                // nothing to do
                break;
            case JsonSchema::INTEGER:
                $bitwiseHandling = $prop->getMeta(PolicyDataSchemaMetaName::FIELD_ON_INPUT_BITWISE_HANDLING->value);
                $v = [];
                $again = true;
                while ($again) {
                    $value = $this->askJsonSchemaPrimitive(
                        $io,
                        "Add to $propName ($prop->type)",
                        $prop->type,
                        $prop->default ?? null
                    );
                    $v[] = $bitwiseHandling ? pow(2, $value-1) : $value;
                    if ($bitwiseHandling) {
                        $propValue = array_reduce($v, fn($carry, $item) => $carry | $item, 0);
                    } else {
                        $propValue = $v[0];
                    }
                    $io->writeln($propName.': '.$propValue.($bitwiseHandling ? ' (bitwise)' : ''));
                    $again = $bitwiseHandling && $io->confirm("Add more to $propName?", false);
                }
                break;
            default:
                $propValue = $this->askJsonSchemaPrimitive(
                    $io,
                    "Enter $propName ($prop->type)",
                    $prop->type,
                    $prop->default ?? null
                );
                break;
        }
        if ($prop->type !== JsonSchema::INTEGER) { // to prevent duplicate output
            $io->writeln($propName.': '.json_encode($propValue));
        }
        return $propValue;
    }

    private function askJsonSchemaPrimitive($io, string $question, string $primitive, ?string $default = null): mixed
    {
        $primitives = [JsonSchema::BOOLEAN, JsonSchema::INTEGER, JsonSchema::NUMBER, JsonSchema::STRING];
        assert(in_array($primitive, $primitives));
        while (1) {
            try {
                switch ($primitive) {
                    case JsonSchema::BOOLEAN:
                        return $io->confirm($question, $default ?? true);
                    case JsonSchema::INTEGER:
                        return (int)$io->ask($question, $default, fn($s) => ctype_digit($s) ? $s :
                             throw new \RuntimeException('Please enter an integer'));
                    case JsonSchema::NUMBER:
                        return (float)$io->ask($question, $default, fn($s) => is_numeric($s) ? $s :
                             throw new \RuntimeException('Please enter a valid number'));
                    case JsonSchema::STRING:
                        return $io->ask($question, $default);
                }
            } catch (\Exception $e) {
                $io->error($e->getMessage());
            }
        }
    }

    /**
     * @param SymfonyStyle $io
     * @param PolicyFilterTypeName[] $policyFilterTypeNames
     * @return ?PolicyFilterTypeName
     */
    public function askPolicyFilterTypeName(SymfonyStyle $io, array $policyFilterTypeNames): ?PolicyFilterTypeName
    {
        $n = 0;
        foreach ($policyFilterTypeNames as $policyFilterTypeName) {
            $choices[$n++] = $policyFilterTypeName->value;
        }
        $choices[$n] = 'Skip, no filter';
        $policyFilterTypeName = null;
        if ($n !== $choice = self::ioChoice($io, 'Choose a policy filter', $choices, current($choices))) {
            $policyFilterTypeName = $choices[$choice];
        }
        if (null === $policyFilterTypeName) {
            return null;
        }
        return PolicyFilterTypeName::from($policyFilterTypeName);
    }

    /**
     * @throws \Exception
     */
    public function askPolicyData(SymfonyStyle $io, PolicyTypeName $policyTypeName, array $context): array
    {
        $policyTypeDisplayName = PolicyTypeName::getDescription($policyTypeName);
        $schema = PolicyDataFactory::getPolicyDataSchemaByType($policyTypeName);
        $result = ['type' => $policyTypeName->value];
        foreach ($schema->getProperties() as $propertyName => $property) {
            if ($propertyName == 'type') { // this is the policy type which we already know.
                continue;
            }
            $result[$propertyName] = $this->askJsonSchemeProperty(
                $io,
                $policyTypeDisplayName. ' '.$propertyName,
                $property,
                $context
            );
            $io->writeln($propertyName.': '.json_encode($result[$propertyName]));
        }
        $io->writeln(json_encode($result));
        return $result;
    }

    public function askPolicyTypeName(SymfonyStyle $io): PolicyTypeName
    {
        $choices = [];
        $n = 0;
        foreach (PolicyTypeName::cases() as $policyTypeName) {
            // @note(MH): the dot is force Symfony to use a map such that it returns the chosen key instead of the value
            //  we do not want it return the display name.
            $choices[($n++).'.'] = PolicyTypeName::getDescription($policyTypeName);
        }
        $choice = (int)$io->choice('Choose a policy type', $choices, key($choices));
        return PolicyTypeName::cases()[$choice];
    }

    /**
     * @param int $gameSessionId
     * @param Layer $layer
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param SymfonyStyle $io
     * @param string|null $default
     * @return string|null
     * @throws Exception
     */
    public function askLayerGeometryName(
        int $gameSessionId,
        Layer $layer,
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ?string $default = null
    ): ?string {
        $names = collect($this->connectionManager->getCachedGameSessionDbConnection($gameSessionId)
            ->executeQuery(
                <<< 'SQL'
                SELECT r.geometry_name
                FROM (
                    SELECT
                      DISTINCT
                      CONCAT(
                        IFNULL(JSON_EXTRACT(geometry_data, '$.name'), ''),
                        IFNULL(JSON_EXTRACT(geometry_data, '$.NAME'), ''),
                        IFNULL(JSON_EXTRACT(geometry_data, '$.Name'), '')
                    ) as geometry_name
                   FROM geometry WHERE geometry_layer_id = :layerId
                ) as r
                WHERE r.geometry_name != '""' and r.geometry_name != ''
                ORDER BY r.geometry_name
                SQL,
                ['layerId' => $layer->getLayerId()]
            )->fetchFirstColumn())->map(fn($name) => json_decode($name))->toArray();
        if (empty($names)) {
            return null;
        }
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $defaultValue = in_array($default, $names) ? $default : current($names);
        $question = new Question(
            " \e[32mChoose a ".$layer->getLayerGeoType()->value."\e[39m [\e[33m$defaultValue\e[39m]:".PHP_EOL.'> ',
            $defaultValue
        );
        $question->setValidator(function ($answer) use ($names, $layer) {
            if (!in_array($answer, $names)) {
                throw new \RuntimeException('Non-exisiting '.$layer->getLayerGeoType()->value.': '.$answer);
            }
            return $answer;
        });
        return $this->askAndValidateName($question, $names, $helper, $input, $output, $io);
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
    private function cleanUpPreviousPlan(): void
    {
        $em = $this->getGameSessionEntityManager();
        $em->createQueryBuilder()->delete('App:Geometry', 'g')->where('g.geometryFID LIKE :fid')
            ->setParameter('fid', self::TEST_DATA_PREFIX.'%')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PlanPolicy', 'pp')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Plan', 'p')->where('p.planName LIKE :planName')
            ->setParameter('planName', self::TEST_DATA_PREFIX.'%')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Layer', 'l')->where('l.layerName LIKE :layerName')
            ->setParameter('layerName', self::TEST_DATA_PREFIX.'%')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Policy', 'p')->getQuery()->execute();
        $em->flush();
    }

    /**
     * array<PolicyType> $policyTypes
     * array<PolicyFilterType> $policyFilterTypes
     *
     * @param int $gameSessionId
     * @param int $planGameTime
     * @param Layer $layer
     * @param string|null $layerGeometryName
     * @param array $geometrySupportingLayerTypes
     * @param PolicyTypeName $policyTypeName
     * @param array $policyData
     * @param PolicyTarget $policyTarget
     * @return Plan
     * @throws Exception
     * @throws \Exception
     */
    private function createPlan(
        int            $gameSessionId,
        int            $planGameTime,
        Layer          $layer,
        ?string        $layerGeometryName,
        array          $geometrySupportingLayerTypes,
        PolicyTypeName $policyTypeName,
        array          $policyData,
        PolicyTarget   $policyTarget
    ): Plan {
        $this->cleanUpPreviousPlan();
        $geometry = $this->connectionManager->getCachedGameSessionDbConnection($gameSessionId)
            ->executeQuery(
                <<< 'SQL'
                SELECT * FROM geometry
                 WHERE JSON_EXTRACT(geometry_data, '$.NAME') = :geometryName OR
                  JSON_EXTRACT(geometry_data, '$.name') = :geometryName OR
                  JSON_EXTRACT(geometry_data, '$.Name') = :geometryName
                SQL,
                ['geometryName' => $layerGeometryName]
            )->fetchAssociative();
        if ($geometry === false) {
            throw new \Exception('MPA not found');
        }
        $plan = new Plan();
        $this->getGameSessionEntityManager()->wrapInTransaction(function () use (
            $layer,
            $planGameTime,
            $geometry,
            $geometrySupportingLayerTypes,
            $policyTypeName,
            $policyData,
            $policyTarget,
            $plan
        ) {
            // create policy data object from array
            $policyData = $this->createPolicyData($policyTypeName, $policyData);
            $em = $this->getGameSessionEntityManager();
            $plan
                ->setPlanName(self::TEST_DATA_PREFIX.'-'.uniqid())
                ->setCountry($em->getReference(Country::class, 1))
                ->setPlanDescription('')
                ->setPlanTime(new \DateTime())
                ->setPlanGametime($planGameTime)
                ->setPlanState(PlanState::APPROVED)
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
                ->setOriginalLayer($em->getReference(Layer::class, $layer->getLayerId()))
                ->setLayerActive(1)
                ->setLayerSelectable(1)
                ->setLayerActiveOnStart(0)
                ->setLayerToggleable(1)
                ->setLayerEditable(1)
                ->setLayerName(self::TEST_DATA_PREFIX.'-'.uniqid())
                ->setLayerGeotype(null)->setLayerShort('')->setLayerGroup('')->setLayerTooltip('')
                ->setLayerCategory($layer->getLayerCategory())
                ->setLayerSubcategory($layer->getLayerSubcategory())
                ->setLayerKpiCategory($layer->getLayerKpiCategory())
                ->setLayerType(null)
                ->setLayerDepth(1)
                ->setLayerInfoProperties(null)
                ->setLayerTextInfo('{}')
                ->setLayerStates($layer->getLayerStates())
                ->setLayerLastupdate(100)
                ->setLayerEditingType(null)
                ->setLayerSpecialEntityType($layer->getLayerSpecialEntityType())
                ->setLayerGreen(0)->setLayerMelupdateConstruction(0)->setLayerFilecreationtime(0)->setLayerMedia(null)
                ->setLayerEntityValueMax(null)->setLayerTags(null);
            $layerEntity->getOriginalLayer()->setLayerMelupdate(1);
            $em->persist($layerEntity);
            $geometryEntity = new Geometry();
            $geometryData = json_decode($geometry['geometry_data'], true);
            if ($policyTarget === PolicyTarget::GEOMETRY) {
                $geometryData['policies'] ??= [];
                $geometryData['policies'][] = $policyData;
            }
            $geometryEntity
                ->setLayer($layerEntity)
                ->setOriginalGeometry($em->getReference(Geometry::class, $geometry['geometry_id']))
                ->setGeometryFID(self::TEST_DATA_PREFIX.'-'.uniqid())
                ->setGeometryGeometry($geometry['geometry_geometry'])
                ->setGeometryData(json_encode($geometryData))
                ->setCountry($em->getReference(Country::class, 7))
                ->setGeometryActive(1)
                ->setGeometryToSubtractFrom(null)
                ->setGeometryDeleted(0)
                ->setGeometryMspid(null)
                ->setGeometryType(implode(',', $geometrySupportingLayerTypes));
            $em->persist($geometryEntity);
            $planLayer = new PlanLayer();
            $planLayer->setLayer($layerEntity)->setPlan($plan)->setPlanLayerState(PlanLayerState::ACTIVE);
            // no need to persist, it's cascaded
            if ($policyTarget === PolicyTarget::PLAN) {
                $policy = new Policy();
                $policy
                    ->setType($policyTypeName)
                    ->setData($policyData->jsonSerialize());
                $em->persist($policy);
                // no need to persist, it's cascaded
                $planPolicy = new PlanPolicy();
                $planPolicy
                    ->setPlan($plan)
                    ->setPolicy($policy);
            }
        });
        return $plan;
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws InvalidValue
     */
    private function createPolicyData(
        PolicyTypeName $policyTypeName,
        array $policyData
    ): PolicyBasePolicyData {
        $jsonObj = new \stdClass();
        foreach ($policyData as $key => $value) {
            $jsonObj->$key = $value;
        }
        $jsonObj->type = $policyTypeName->value;
        return PolicyDataFactory::createPolicyDataByJsonObject($jsonObj);
    }
}
