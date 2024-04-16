<?php

namespace App\Repository;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\NormalizerContextBuilder;
use App\Entity\Layer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class LayerRepository extends EntityRepository
{
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
                    'layerGeoType' => $layer->getLayerGeoType()?->value ?? '',
                    'geometry' => $layer->exportToDecodedGeoJSON()
                ];
            }
        }
        return $layerReturn ?? [];
    }

    public function getAllVectorLayerIds(): array
    {
        $qb = $this->createQueryBuilder('l');
        return $qb
            ->select('l.layerId')
            ->where($qb->expr()->in('l.layerGeoType', ['polygon', 'line', 'point']))
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

    /**
     * @throws ExceptionInterface|ReflectionException
     */
    public static function createLayerFromData(array $layerData): Layer
    {
        // fix name inconsistencies
        $layerData['layer_geo_type'] = $layerData['layer_geotype'];
        unset($layerData['layer_geotype']);
        $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        return $normalizer->denormalize(
            $layerData,
            Layer::class,
            null,
            (new NormalizerContextBuilder(Layer::class))->withCallbacks([
                'layerGeoType' => fn($value) => LayerGeoType::from($value)
            ])->toArray()
        );
    }
}
