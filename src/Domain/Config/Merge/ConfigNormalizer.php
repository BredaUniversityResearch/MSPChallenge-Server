<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;

/**
 * Brings a complete (legacy) config in the form a region config produces after merging, so the two can be
 * compared: CEL, SEL and MEL are moved into simulation_settings, layer_width, layer_height,
 * layer_raster_filter_mode and layer_information are removed from every layer, "valueDefinitions" are
 * removed from the MEL ecologyCategories and restriction map keys are rebuilt as startlayer|endlayer.
 */
final class ConfigNormalizer
{
    public function normalize(\stdClass $config): \stdClass
    {
        $config = RegionConfigMerger::toSimulationSettings($config);
        $datamodel = $config->datamodel ?? null;
        if (!$datamodel instanceof \stdClass) {
            return $config;
        }
        foreach (is_array($datamodel->meta ?? null) ? $datamodel->meta : [] as $layer) {
            foreach (ConfigSplitter::REMOVED_LAYER_KEYS as $key) {
                unset($layer->{$key});
            }
        }
        $settings = $datamodel->simulation_settings ?? null;
        $mel = $settings instanceof \stdClass ? ($settings->MEL ?? null) : null;
        if ($mel instanceof \stdClass) {
            foreach (is_array($mel->ecologyCategories ?? null) ? $mel->ecologyCategories : [] as $category) {
                if ($category instanceof \stdClass) {
                    unset($category->valueDefinitions);
                }
            }
        }
        if (($datamodel->restrictions ?? null) instanceof \stdClass) {
            $datamodel->restrictions = LayerReferences::groupRestrictions(
                LayerReferences::flattenRestrictions($datamodel->restrictions)
            );
        }
        return $config;
    }
}
