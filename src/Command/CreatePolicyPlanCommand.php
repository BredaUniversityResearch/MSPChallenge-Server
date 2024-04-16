<?php

namespace App\Command;

use App\Domain\Common\EntityEnums\FieldType;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanState;
use App\Domain\Common\EntityEnums\PolicyTypeDataType;
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
use App\Entity\PolicyFilter;
use App\Entity\PolicyFilterLink;
use App\Entity\PolicyFilterType;
use App\Entity\PolicyType;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
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
        $policyTypes = $this->getPolicyTypes();
        if (empty($policyTypes)) {
            throw new \Exception('No policy types found');
        }
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
        $geometryBannedFleets = null;
        if ($layer->getLayerGeoType() == LayerGeoType::RASTER) {
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
            $geometryBannedFleets = $this->askBannedFleets($io, $context, true);
        }
        // assuming the display name to be unique
        $policyTypeName = $this->askPolicyTypeName($io);
        $policyType = $policyTypes[$policyTypeName];
        $policyValue = $this->askPolicyValue($io, $policyType);
        $policyFilterTypes = $this->getPolicyFilterTypes($policyType);
        $policyFilters = [];
        if (!empty($policyFilterTypes)) {
            $policyFilters = $this->askPolicyFilters($io, $policyFilterTypes, $context);
        }
        try {
            $plan = $this->createPlan(
                $gameSessionId,
                $planGameTime,
                $policyTypes,
                $policyFilterTypes,
                $layer,
                $layerGeometryName,
                $geometryBannedFleets,
                $policyTypeName,
                $policyValue,
                $policyFilters
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
     * @param SymfonyStyle $io
     * @param array $policyFilterTypes
     * @param array $context
     * @return array
     * @throws \Exception
     */
    public function askPolicyFilters(SymfonyStyle $io, array $policyFilterTypes, array $context): array
    {
        $policyFilters = [];
        while (true) {
            $linkFilter = $io->confirm(count($policyFilters) > 0 ? "Link another filter?" : "Link a filter?");
            if (!$linkFilter) {
                break;
            }
            if (null === $policyFilterTypeName = $this->askPolicyFilterTypeName($io, $policyFilterTypes)) {
                continue;
            }
            $policyFilterValue = $this->askPolicyFilterTypeValue($policyFilterTypeName, $io, $context);
            $policyFilters[$policyFilterTypeName][] = $policyFilterValue;
        }
        return $policyFilters;
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
     */
    public function askBannedFleets(SymfonyStyle $io, array $context, bool $canBeNone = false): int
    {
        return $this->askLayerType($io, 'Choose banned fleets', $context, $canBeNone);
    }

    public static function generatePermutations(int $n, bool $oneBased = false): array
    {
        $permutations = [];
        for ($i = 1; $i < (1 << $n); $i++) {
            $permutation = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i & (1 << $j)) {
                    $permutation[] = $j + ($oneBased ? 1 : 0);
                }
            }
            $permutations[] = $permutation;
        }
        return $permutations;
    }

    public function askLayerType(SymfonyStyle $io, string $question, array $context, bool $canBeNone = false): int
    {
        if (!array_key_exists('layerName', $context)) {
            throw new \Exception('Layer name not found in the context of question: '.$question);
        }
        if (!array_key_exists('gameSessionId', $context)) {
            throw new \Exception('Game session ID not found in the context of question:'.$question);
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

        if ($canBeNone) {
            $choices[0] = $layerConfig['layer_type'][0]['displayName'];
        }
        $permutations = self::generatePermutations(count($layerConfig['layer_type'])-1, true);
        foreach ($permutations as $permutation) {
            $displayName = '';
            $key = 0;
            foreach ($permutation as $index) {
                $key |= (2 ** ($index-1));
                $displayName .= $layerConfig['layer_type'][$index]['displayName'] . ' + ';
            }
            // Remove the trailing ' + ' from the display name
            $displayName = rtrim($displayName, ' + ');
            $choices[$key] = $displayName;
        }
        assert(!empty($choices));
        return self::ioChoice($io, $question, $choices, current($choices));
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
    public function askPolicyFilterTypeValue(string $policyFilterTypeName, SymfonyStyle $io, array $context): mixed
    {
        switch ($policyFilterTypeName) {
            case 'fleet':
                return $this->askLayerType($io, 'Choose banned fleets', $context);
            case 'schedule':
                return $this->askSchedule($io, $context);
        }
        throw new \Exception('Unknown policy filter type: '.$policyFilterTypeName);
    }

    private function parseMonths(string $monthsString): array
    {
        if (ctype_digit($monthsString)) {
            return [(int)$monthsString];
        }
        $months = [];
        $ranges = explode(',', $monthsString);
        foreach ($ranges as $range) {
            [$start, $end] = explode('-', $range);
            $months = array_merge($months, range((int)$start, (int)$end));
        }
        return $months;
    }

    /**
     * @param SymfonyStyle $io
     * @param array{planGameTime: int} $context
     * @return array
     */
    private function askSchedule(SymfonyStyle $io, array $context): array
    {
        $nextMonth = ($context['planGameTime'] % 12) + 1;
        $nextNextMonth = ($nextMonth % 12) + 1;
        $monthsString = $io->ask(
            'Enter nothing, a single month, or more in format n-n,n-n,... (e.g., 1-3,6-11): ',
            "$nextMonth-$nextNextMonth", // 1-based
            function ($input) {
                $pattern = "/^(\d{1,2}(-\d{1,2})?(,)?)*$/";
                if (!preg_match($pattern, $input)) {
                    throw new \RuntimeException(
                        'Invalid input format. Enter nothing, a single month or more in format n-n,n-n,...'
                    );
                }
                return $input;
            }
        );
        return $this->parseMonths($monthsString);
    }

    /**
     * @param SymfonyStyle $io
     * @param array<PolicyFilterType> $policyFilterTypes
     * @return ?string
     */
    public function askPolicyFilterTypeName(SymfonyStyle $io, array $policyFilterTypes): ?string
    {
        $n = 0;
        foreach ($policyFilterTypes as $policyFilterType) {
            $choices[$n++] = $policyFilterType->getName();
        }
        $choices[$n] = 'Skip, no filter';
        $policyFilterTypeName = null;
        if ($n !== $choice = self::ioChoice($io, 'Choose a policy filter', $choices, current($choices))) {
            $policyFilterTypeName = $choices[$choice];
        }
        return $policyFilterTypeName;
    }

    public function askPolicyValue(SymfonyStyle $io, PolicyType $policyType): mixed
    {
        $policyTypeDisplayName = $policyType->getDisplayName();
        if ($policyType->getDataType() === PolicyTypeDataType::Boolean) {
            return $io->confirm("Enable $policyTypeDisplayName?");
        }
        if ($policyType->getDataType() !== PolicyTypeDataType::Ranged) {
            return null;
        }
        $policyValue = null;
        while ($policyValue === null) {
            $policyValue = $io->ask("Enter a $policyTypeDisplayName value", '40000');
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
        $choices = [];
        $n = 0;
        $policyTypes = $this->getPolicyTypes();
        foreach ($policyTypes as $policyType) {
            // @note(MH): the dot is force Symfony to use a map such that it returns the chosen key instead of the value
            //  we do not want it return the display name.
            $choices[($n++).'.'] = $policyType->getDisplayName();
        }
        assert(!empty($choices));
        $choice = (int)$io->choice('Choose a policy type', $choices, key($choices));
        return array_values($policyTypes)[$choice]->getName();
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
            " \e[32mChoose a ".($layer->getLayerGeoType()?->value ?? 'geometry').
                "\e[39m [\e[33m$defaultValue\e[39m]:".PHP_EOL.'> ',
            $defaultValue
        );
        $question->setValidator(function ($answer) use ($names, $layer) {
            if (!in_array($answer, $names)) {
                throw new \RuntimeException('Non-exisiting '.
                    ($layer->getLayerGeoType()?->value ?? 'geometry').': '.$answer);
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
        $em->createQueryBuilder()->delete('App:PolicyFilter', 'pf')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterLink', 'pfl')->getQuery()->execute();
        $em->flush();
    }

    /**
     * Retrieves the policy types incl. its filters keyed by the name
     *
     * @return array<PolicyType>
     */
    private function getPolicyTypes(): array
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }
        $em = $this->getGameSessionEntityManager();
        $result = collect($em->createQueryBuilder()
            ->select('pt, ptft, pft')
            ->from(PolicyType::class, 'pt')
            ->leftJoin('pt.policyTypeFilterTypes', 'ptft')
            ->leftJoin('ptft.policyFilterType', 'pft')
            ->getQuery()->getResult())->keyBy(fn(PolicyType $pt) => $pt->getName())->all();
        return $result;
    }

    /**
     * returns the policy filter types for a given policy type keyed by the filter type name
     *
     * @param PolicyType $policyType
     * @return array<PolicyFilterType>
     */
    private function getPolicyFilterTypes(PolicyType $policyType): array
    {
        $policyFilterTypes = [];
        $policyTypeFilterTypes = $policyType->getPolicyTypeFilterTypes();
        foreach ($policyTypeFilterTypes as $policyTypeFilterType) {
            $policyFilterTypes[$policyTypeFilterType->getPolicyFilterType()->getName()] =
                $policyTypeFilterType->getPolicyFilterType();
        }
        return $policyFilterTypes;
    }

    private function processPolicyFilterValue(PolicyFilterType $policyFilterType, mixed $policyFilterValue): mixed
    {
        switch ($policyFilterType->getFieldType()) {
            case FieldType::SMALLINT:
                return (int) $policyFilterValue;
            case FieldType::BOOLEAN:
                return (bool) $policyFilterValue;
            case FieldType::JSON: // fall-through
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
     * array<PolicyType> $policyTypes
     * array<PolicyFilterType> $policyFilterTypes
     *
     * @param int $gameSessionId
     * @param int $planGameTime
     * @param array $policyTypes
     * @param array $policyFilterTypes
     * @param Layer $layer
     * @param string|null $layerGeometryName
     * @param int|null $geometryBannedFleets
     * @param string $policyTypeName
     * @param mixed $policyValue
     * @param array $policyFilters
     * @return Plan
     * @throws \Exception
     */
    private function createPlan(
        int $gameSessionId,
        int $planGameTime,
        array $policyTypes,
        array $policyFilterTypes,
        Layer $layer,
        ?string $layerGeometryName,
        ?int $geometryBannedFleets,
        string $policyTypeName,
        mixed $policyValue,
        array $policyFilters
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
            $policyTypes,
            $policyFilterTypes,
            $layer,
            $planGameTime,
            $geometry,
            $geometryBannedFleets,
            $policyTypeName,
            $policyValue,
            $policyFilters,
            $plan
        ) {
            $policyType = $policyTypes[$policyTypeName];
            $em = $this->getGameSessionEntityManager();
            $em->persist($policyType);
            $policy = new Policy();
            $policy
                ->setType($policyType)
                ->setValue($policyValue);
            $em->persist($policy);
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
                ->setLayerGeoType(null)->setLayerShort('')->setLayerGroup('')->setLayerTooltip('')
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
            $geometryEntity
                ->setLayer($layerEntity)
                ->setOriginalGeometry($em->getReference(Geometry::class, $geometry['geometry_id']))
                ->setGeometryFID(self::TEST_DATA_PREFIX.'-'.uniqid())
                ->setGeometryGeometry($geometry['geometry_geometry'])
                ->setGeometryData($geometry['geometry_data'])
                ->setCountry($em->getReference(Country::class, 7))
                ->setGeometryActive(1)
                ->setGeometryToSubtractFrom(null)
                ->setGeometryDeleted(0)
                ->setGeometryMspid(null);
            if (null !== $geometryBannedFleets && $geometryBannedFleets !== 0) {
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
            if (empty(array_filter($policyFilters))) {
                return;
            }
            foreach ($policyFilters as $policyFilterTypeName => $policyFilterValues) {
                foreach ($policyFilterValues as $policyFilterValue) {
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
                }
            }
        });
        return $plan;
    }
}
