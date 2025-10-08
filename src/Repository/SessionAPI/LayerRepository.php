<?php

namespace App\Repository\SessionAPI;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityPropToDbColumnNameConvertor;
use App\Domain\Common\NormalizerContextBuilder;
use App\Entity\SessionAPI\Layer;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class LayerRepository extends EntityRepository
{
    public const PLAY_AREA_LAYER_PREFIX = '_PLAYAREA';

    private ?ObjectNormalizer $normalizer = null; // to be created upon usage
    private ?Serializer $serializer = null; // to be created upon usage

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
    public function createLayerFromData(array $layerData): Layer
    {
        // fix name inconsistencies
        $layerData['layer_geo_type'] = $layerData['layer_geotype'];
        unset($layerData['layer_geotype']);
        return $this->getSerializer()->denormalize(
            $layerData,
            Layer::class,
            null,
            (new NormalizerContextBuilder(Layer::class))->withCallbacks([
                'layerGeoType' => fn($value) => LayerGeoType::from($value),
                'layerTextInfo' => fn($value) => $value ?? []
            ])->toArray()
        );
    }

    /**
     * @throws Exception|ExceptionInterface
     */
    public function toArray(?Layer $layer): array
    {
        if (is_null($layer)) {
            return [];
        }
        return $this->getSerializer()->normalize(
            $layer,
            null,
            (new NormalizerContextBuilder(Layer::class))
                ->withAttributes(array_merge(
                    array_keys($this->getEntityManager()->getClassMetadata(Layer::class)->fieldMappings),
                    [
                        'originalLayer'
                    ]
                ))
                ->withCallbacks([
                    'originalLayer' => fn($value) => $value?->getLayerId(),
                    'layerGeoType' => fn(?LayerGeoType $value) => $value?->value
                ])->toArray()
        );
    }

    private function getNormalizer(): ObjectNormalizer
    {
        $this->normalizer ??= new ObjectNormalizer(null, new EntityPropToDbColumnNameConvertor(
            customNameMapping: [
                'layerGeoType' => 'layer_geotype',
                'originalLayer' => 'layer_original_id'
            ]
        ));
        return $this->normalizer;
    }

    private function getSerializer(): Serializer
    {
        $this->serializer ??= new Serializer([$this->getNormalizer()]);
        return $this->serializer;
    }
}
