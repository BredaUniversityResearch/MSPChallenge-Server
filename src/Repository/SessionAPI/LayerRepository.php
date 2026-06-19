<?php

namespace App\Repository\SessionAPI;

use App\Domain\Common\CustomMappingNameConvertor;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\NormalizerContextBuilder;
use App\Entity\SessionAPI\Layer;
use App\Entity\SessionAPI\LayerRaster;
use App\Entity\SessionAPI\LayerTextInfo;
use Doctrine\ORM\NonUniqueResultException;
use ReflectionException;

/**
 * @extends SessionEntityRepository<Layer>
 */
class LayerRepository extends SessionEntityRepository
{
    public const PLAY_AREA_LAYER_PREFIX = '_PLAYAREA';

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
                    'layerGeoType' => $layer->getLayerGeoType()->value ?? '',
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

    protected function onPreDenormalize(array $data): array
    {
        // fix name inconsistencies
        $data['layer_geo_type'] = $data['layer_geotype'];
        $layerRasterFields = [
            'layer_raster_material',
            'layer_raster_pattern',
            'layer_raster_minimum_value_cutoff',
            'layer_raster_color_interpolation',
            'layer_raster_filter_mode'
        ];
        foreach ($layerRasterFields as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            $data['layer_raster'][$field] = $data[$field];
            unset($data[$field]);
        }
        unset($data['layer_geotype']);
        return $data;
    }

    /**
     * @throws ReflectionException
     */
    protected function initDenormalizerContextBuilder(
        NormalizerContextBuilder $contextBuilder
    ): NormalizerContextBuilder {
        return $contextBuilder->withCallbacks([
            'layerGeoType' => fn($value) => LayerGeoType::from($value),
            'layerTextInfo' => fn($value) => $value ?? new LayerTextInfo(),
            'layerRaster' => fn($value) => $value === null ? $value : new LayerRaster($value)
        ]);
    }

    /**
     * @throws ReflectionException
     */
    protected function initNormalizerContextBuilder(
        NormalizerContextBuilder $contextBuilder
    ): NormalizerContextBuilder {
        return $contextBuilder
            ->withAttributes(array_merge(
                array_keys($this->getEntityManager()->getClassMetadata(Layer::class)->fieldMappings),
                [
                    'originalLayer','layerDependencies','scale'
                ]
            ))
            ->withCallbacks([
                'originalLayer' => fn($value) => $value?->getLayerId(),
                'layerGeoType' => fn(?LayerGeoType $value) => $value?->value
            ])
            ->withPreserveEmptyObjects(true)
            ->withSkipNullValues(true);
    }

    protected function initNameConvertor(CustomMappingNameConvertor $convertor): CustomMappingNameConvertor
    {
        return $convertor->setCustomMapping([
            'layerGeoType' => 'layer_geotype',
            'originalLayer' => 'layer_original_id'
        ]);
    }
}
