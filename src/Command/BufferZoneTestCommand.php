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
use Brick\Geo\Engine\PDOEngine;
use Brick\Geo\IO\GeoJSON\Feature;
use Brick\Geo\IO\GeoJSONReader;
use Brick\Geo\IO\GeoJSONWriter;
use Brick\Geo\MultiPolygon;
use Doctrine\ORM\Exception\ORMException;
use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:buffer-zone-test',
    description: 'Add a short description for your command',
)]
class BufferZoneTestCommand extends Command
{
    public function __construct(private readonly ConnectionManager $connectionManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    /**
     * @throws ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->connectionManager->getGameSessionEntityManager(1);
        $em->createQueryBuilder()->delete('App:Geometry', 'g')->where('g.geometryFID = :fid')
            ->setParameter('fid', 'bufferzonetest')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PlanPolicy', 'pp')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Plan', 'p')->where('p.planName = :planName')
            ->setParameter('planName', 'bufferzonetest')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyLayer', 'pl')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Layer', 'l')->where('l.layerName = :layerName')
            ->setParameter('layerName', 'bufferzonetest')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:Policy', 'p')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilter', 'pf')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyTypeFilterType', 'ptft')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterLink', 'pfl')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyFilterType', 'pft')->getQuery()->execute();
        $em->createQueryBuilder()->delete('App:PolicyType', 'pt')->getQuery()->execute();
        $em->flush();

// @todo
//        $policyType2 = new PolicyType();
//        $policyType2
//            ->setName('gear')
//            ->setDisplayName('Ecological fishing gear')
//            ->setDataType(PolicyTypeDataType::Boolean)
//            ->setDataConfig(false);

        $gameCurrentMonth = (int)$em->createQueryBuilder()->select('g.gameCurrentmonth')->from('App:Game', 'g')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
        $em->wrapInTransaction(function () use ($gameCurrentMonth) {
            $em = $this->connectionManager->getGameSessionEntityManager(1);
            $policyType = new PolicyType();
            $policyType
                ->setName('buffer')
                ->setDisplayName('Buffer zone')
                ->setDataType(PolicyTypeDataType::Ranged)
                ->setDataConfig(['min' => 0, 'unit_step_size' => 10000, 'max' => 100000]);
            $em->persist($policyType);
            $policy = new Policy();
            $policy
                ->setType($policyType)
                ->setValue(40000);
            $em->persist($policy);
            $plan = new Plan();
            $plan
                ->setPlanName('bufferzonetest')
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
            $layer = new Layer();
            $layer
                ->setOriginalLayer($em->getReference(Layer::class, 66))
                ->setLayerActive(1)
                ->setLayerSelectable(1)
                ->setLayerActiveOnStart(0)
                ->setLayerToggleable(1)
                ->setLayerEditable(1)
                ->setLayerName('bufferzonetest')
                ->setLayerGeotype('')->setLayerShort('')->setLayerGroup('')->setLayerTooltip('')
                ->setLayerCategory('management')
                ->setLayerSubcategory('aquaculture')
                ->setLayerKpiCategory('Miscellaneous')
                ->setLayerType(null)
                ->setLayerDepth(1)
                ->setLayerInfoProperties(null)
                ->setLayerTextInfo('{}')
                ->setLayerStates(
                    '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]'
                )
                ->setLayerRaster(null)
                ->setLayerLastupdate(100)
                ->setLayerEditingType(null)
                ->setLayerSpecialEntityType('Default')
                ->setLayerGreen(0)->setLayerMelupdateConstruction(0)->setLayerFilecreationtime(0)->setLayerMedia(null)
                ->setLayerEntityValueMax(null)->setLayerTags(null);
            $layer->getOriginalLayer()->setLayerMelupdate(1);
            $em->persist($layer);
            $geometry = new Geometry();
            $geometry
                ->setLayer($layer)
                ->setOriginalGeometry($em->getReference(Geometry::class, 9909)) // MPA "Friese Front"
                ->setGeometryFID('bufferzonetest')
                ->setGeometryGeometry(
                    '[[4006915.7151352,3423828.6711378],[3936851.7665632,3382824.6408395],'.
                    '[3940217.8467551,3425397.3489081],[4010277.2493931,3467535.6859769],'.
                    '[4006915.7151352,3423828.6711378]]'
                )
                ->setGeometryData(
                    '{"NO_TAKE":"","METADATAID":"","DESIG_TYPE":"Regional","MANG_PLAN":"","MARINE":"","GIS_AREA":"",'.
                    '"ISO3":"","SUB_LOC":"","OWN_TYPE":"","Type_1":"No protection against fishing","STATUS":"",'.
                    '"GOV_TYPE":"","WDPA_PID":"","ORIG_NAME":"Friese Front","VERIF":"","STATUS_YR":"",'.
                    '"NAME":"Friese Front","MANG_AUTH":"","NO_TK_AREA":"","WDPAID":null,"DESIG":"","REP_M_AREA":null,'.
                    '"GIS_M_AREA":null,"REP_AREA":null,"original_layer_name":"NS_Marine_Protected_Areas"}'
                )
                ->setCountry($em->getReference(Country::class, 7))
                ->setGeometryActive(1)
                ->setGeometryToSubtractFrom(null)
                //->setGeometryType('1,2,3')
                ->setGeometryDeleted(0)
                ->setGeometryMspid(null);
            $em->persist($geometry);
            $planLayer = new PlanLayer();
            $planLayer->setLayer($layer)->setPlan($plan)->setPlanLayerState('ACTIVE');
            // no need to persist, it's cascaded
            $planPolicy = new PlanPolicy();
            $planPolicy
                ->setPlan($plan)
                ->setPolicy($policy);
            // no need to persist, it's cascaded
            $policyLayer = new PolicyLayer();
            $policyLayer
                ->setLayer($layer)
                ->setPolicy($policy);
            // no need to persist, it's cascaded
            $policyFilterType = new PolicyFilterType();
            $policyFilterType
                ->setName('fleet')
                ->setFieldType(FieldType::SMALLINT);
            $em->persist($policyFilterType);
            $policyFilter = new PolicyFilter();
            $policyFilter
                ->setType($policyFilterType)
                ->setValue(1);
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

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
