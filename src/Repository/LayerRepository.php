<?php

namespace App\Repository;

use App\Entity\Layer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;

class LayerRepository extends EntityRepository
{
    public const PLAY_AREA_LAYER_PREFIX = '_PLAYAREA';

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getAllGeometryDecodedGeoJSON(): array
    {
        $layers = $this->getAllVectorLayerIds();
        foreach ($layers as $layer) {
            $layer = $this->getLayerCurrentAndPlannedGeometry($layer['layerId']);
            if (!is_null($layer)) {
                $layerReturn[$layer->getLayerName()] = [
                    'layerGeoType' => $layer->getLayerGeoType(),
                    'geometry' => $layer->exportToDecodedGeoJSON()
                ];
            }
        }
        return $layerReturn ?? [];
    }

    /**
     * @return Layer[]
     */
    public function getPlayAreaLayers(): array
    {
        $qb = $this->createQueryBuilder('l');
        return $qb
            ->where('l.layerName LIKE :name')
            ->setParameter('name', self::PLAY_AREA_LAYER_PREFIX.'%')
            ->getQuery()
            ->getResult();
    }

    public function getAllVectorLayerIds(): array
    {
        $qb = $this->createQueryBuilder('l');
        return $qb
            ->select('l.layerId')
            ->where($qb->expr()->in('l.layerGeotype', ['polygon', 'line', 'point']))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('l.originalLayer', 0),
                $qb->expr()->isNull('l.originalLayer')
            ))
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getLayerCurrentAndPlannedGeometry(int $layerId): ?Layer
    {
        // the hydrated results make a single all-layer query too heavy, hence only doing this for one layer
        $qb = $this->createQueryBuilder('l');
        return $qb
            ->addSelect('g')
            ->addSelect('gder')
            ->addSelect('gsub')
            ->addSelect('l2')
            ->addSelect('g2')
            ->addSelect('g2der')
            ->leftJoin('l.geometry', 'g')
            ->leftJoin('g.geometrySubtractives', 'gsub')
            ->leftJoin('g.derivedGeometry', 'gder')
            ->leftJoin('l.derivedLayer', 'l2')
            ->leftJoin('l2.geometry', 'g2')
            ->leftJoin('g2.derivedGeometry', 'g2der')
            ->leftJoin('l2.planLayer', 'pl')
            ->leftJoin('pl.plan', 'p')
            ->where($qb->expr()->eq('l.layerId', ':layerId'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('g.geometryActive', 1),
                $qb->expr()->eq('g2.geometryActive', 1)
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('p.planState', ':approved'),
                $qb->expr()->eq('p.planState', ':implemented'),
                $qb->expr()->isNull('p.planState')
            ))
            ->setParameter('layerId', $layerId)
            ->setParameter('approved', 'APPROVED')
            ->setParameter('implemented', 'IMPLEMENTED')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Layer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Layer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
