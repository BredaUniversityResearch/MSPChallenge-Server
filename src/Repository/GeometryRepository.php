<?php

namespace App\Repository;

use App\Entity\Geometry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class GeometryRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    /**
     * @return Geometry[] Returns an array of GameList objects by session state, archived or not archived (active)
     */
    public function findDuplicateMspids(int $layerId): array
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        return $this->createQueryBuilder('g')
            ->select('g.geometryMspid')
            ->addSelect('g.geometryGeometry')
            ->addSelect('g.geometryData')
            ->addSelect('l.layerId')
            ->addSelect('l.layerName')
            ->innerJoin('g.layer', 'l')
            ->where(
                $expr->in(
                    'g.geometryMspid',
                    $this->createQueryBuilder('g2')
                        ->select('g2.geometryMspid')
                        ->groupBy('g2.geometryMspid')
                        ->where($expr->isNotNull('g2.geometryMspid'))
                        ->having('COUNT(g2.geometryMspid) > 1')
                        ->getDQL()
                )
            )
            ->andWhere($expr->eq('l.layerId', $layerId))
            ->orderBy('g.geometryMspid')
            ->getQuery()
            ->getArrayResult()
            ;
            /*SELECT layer_id, geometry_mspid, geometry_geometry, geometry_data, layer_name
            FROM `geometry`
            INNER JOIN layer ON layer.layer_id = geometry.geometry_layer_id
            WHERE geometry_mspid IN (SELECT geometry_mspid
                                  FROM geometry
                                  GROUP BY geometry_mspid
                                  HAVING COUNT(geometry_mspid) > 1)
            AND layer_id = 93
            ORDER BY geometry_mspid*/
    }

    public function save(Geometry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Geometry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
