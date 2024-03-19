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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $gameSessionId = $input->getOption(self::OPTION_GAME_SESSION_ID);
        if (ctype_digit($gameSessionId)) {
            $gameSessionId = (int) $gameSessionId;
        } else {
            $io->error('The game session ID must be a number');
            return Command::FAILURE;
        }
        $io->write('Creating a policy plan for game session ID: '.$gameSessionId);
        $choices = ['Marine Protected Areas', 'Exit'];
        $layerShort = $io->choice('Choose a layer', $choices, $choices[0]);
        if ($layerShort === 'Exit') {
            return Command::SUCCESS;
        }

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
            $choices = ['Friese front'];
            $layerGeometryName = $io->choice('Choose a '.$layer['layerGeotype'], $choices, $choices[0]);
        }
        $choices = ['Buffer zone'];
        $policyType = $io->choice('Choose a policy type', $choices, $choices[0]);
        $policyValue = null;
        while ($policyValue === null) {
            $policyValue = $io->ask("Enter a $policyType value", '40000');
            if (is_numeric($policyValue) && $policyValue >= 0 && $policyValue <= 100000) {
                $policyValue = (int) $policyValue;
            } else {
                $io->error('The value must be a number between 0 and 100000');
            }
        }
        $choices = ['fleet', 'Skip, no filter'];
        $policyFilterType = $io->choice('Choose a policy filter', $choices, $choices[0]);
        $policyFilterValue = null;
        if ($policyFilterType === 'fleet') {
            $choices = ['Bottom Trawl', 'Industrial and Pelagic Trawl', 'Drift and Fixed Nets'];
            $policyFilterValue = $io->choice('Enter a fleet', $choices, $choices[0]);
            $policyFilterValue = array_search($policyFilterValue, $choices) + 1;
        }
        try {
            $this->createPlan(
                $gameSessionId,
                $layer,
                $layerGeometryName,
                $policyType,
                $policyValue,
                $policyFilterType,
                $policyFilterValue
            );
        } catch (\Exception $e) {
            $io->error('Failed to create plan: '.$e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Plan created successfully.');
        return Command::SUCCESS;
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
        string $policyFilterTypeName,
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
