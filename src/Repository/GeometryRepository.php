<?php

namespace App\Repository;

use App\Entity\Geometry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class GeometryRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getPlayArea(): Geometry
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        return $this->createQueryBuilder('g')
            ->innerJoin('g.layer', 'l')
            ->where(
                $expr->like('l.layerName', ':playarea')
            )
            ->setParameter('playarea', '_PLAYAREA%')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @return array of geometry with duplicate MSP IDs
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
