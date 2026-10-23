<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;

/**
 * Creates the final (effective) config of a region config by merging it with the generic config.
 *
 * Every config is merged the same way, a stripped region config and a complete old-style one (as made by the
 * editor) alike: the generic config is the base and the config is laid on top of it. A complete config has all
 * its own values, so little of the generic config shows through; it only supplies what the config lacks.
 * The result has layers with their region specific layer_name and no msp_config_generic_name, layer
 * references in restrictions/SEL that are region layer names again, and `simulation_settings` that always has
 * CEL, SEL and MEL (null when there is none).
 *
 * Old-style CEL, SEL and MEL keys directly in `datamodel` stay supported. They are laid on top of the
 * `simulation_settings` the generic and region config give, key by key (generic, then the region's
 * simulation_settings, then the old-style key wins), and they are not part of the result. A list in an old-style
 * SEL replaces the list of the other layers: an old-style SEL always holds the complete list. An old-style null
 * means: no simulation.
 *
 * Merge rules (what ConfigSplitter produces):
 *  - objects merge recursively, the region wins; arrays and scalars of the region replace the generic value;
 *    an explicit null replaces too (a region without SEL/MEL has "SEL": null);
 *  - restrictions and SEL.shipping_lane_layers / port_layers / restriction_layer_exceptions are ADDITIVE: generic
 *    items first, then the region's. Generic items that refer to a layer the region does not have are skipped;
 *  - layer_info_properties are merged by their key, property_name: an item of the region with the name of an item
 *    of the generic layer is merged into it (the region wins), the other items of the region come after the
 *    generic ones;
 *  - simulation_settings is open-ended: every key of it is a simulation, and all of them merge in the same way
 *    (objects recursively, the region wins, null means none). Only SEL has rules of its own (the additive lists
 *    above, and its layer references). CEL, SEL and MEL are always in the result, null when there is none;
 *  - dependencies are replaced as a whole;
 *  - a region layer entry without a generic counterpart is used as is.
 * The inputs are never modified. isRegionFormat() only tells a stripped config from a complete one (the commands
 * need that); merge() does not depend on it.
 */
final class RegionConfigMerger
{
    /** Order of the keys in a layer, as in the existing configs (cosmetic only). */
    public const array LAYER_KEY_ORDER = [
        'layer_name', 'layer_geotype', 'layer_entity_value_max', 'layer_short', 'layer_category',
        'layer_subcategory', 'layer_download_from_geoserver', 'layer_width', 'layer_height', 'layer_raster_material',
        'layer_raster_filter_mode', 'layer_raster_color_interpolation', 'layer_raster_pattern',
        'layer_raster_minimum_value_cutoff', 'layer_active', 'layer_selectable', 'layer_editable',
        'layer_toggleable', 'layer_active_on_start', 'layer_green', 'layer_tooltip', 'layer_information',
        'layer_media', 'layer_text_info', 'layer_states', 'layer_editing_type',
        'layer_special_entity_type', 'layer_depth', 'layer_property_as_type', 'layer_type',
        'layer_info_properties', 'layer_tags',
    ];

    /** The simulations that are always in datamodel.simulation_settings of a final config, null when there is none. */
    public const array SIMULATIONS = ['CEL', 'SEL', 'MEL'];

    /**
     * The simulations that configs in the old shape have directly in datamodel. Other simulations (settings of
     * future or external simulations) only exist in datamodel.simulation_settings.
     */
    public const array LEGACY_SIMULATIONS = ['CEL', 'REL', 'SEL', 'MEL'];

    /**
     * Is the config stripped: does it need a parent? That is so when it names a parent, or when it has layers that
     * refer to a generic layer. (A complete config in the new shape is a region format too, but it is not stripped.)
     */
    public static function isStripped(\stdClass $config): bool
    {
        if (($config->metadata ?? null) instanceof \stdClass && isset($config->metadata->parent)) {
            return true;
        }
        $datamodel = $config->datamodel ?? null;
        $layers = $datamodel instanceof \stdClass && is_array($datamodel->meta ?? null) ? $datamodel->meta : [];
        foreach ($layers as $layer) {
            if ($layer instanceof \stdClass && ConfigValues::has($layer, 'msp_config_generic_name')) {
                return true;
            }
        }
        return false;
    }

    public static function isRegionFormat(\stdClass $config): bool
    {
        $datamodel = $config->datamodel ?? null;
        if (!$datamodel instanceof \stdClass) {
            return false;
        }
        if (ConfigValues::has($datamodel, 'simulation_settings')) {
            return true;
        }
        foreach (is_array($datamodel->meta ?? null) ? $datamodel->meta : [] as $layer) {
            if ($layer instanceof \stdClass && ConfigValues::has($layer, 'msp_config_generic_name')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string[] $warnings filled with things that could not be resolved
     */
    public function merge(\stdClass $generic, \stdClass $config, array &$warnings = []): \stdClass
    {
        if (!($config->datamodel ?? null) instanceof \stdClass) {
            return ConfigValues::clone($config);
        }
        $genericDatamodel = $generic->datamodel ?? new \stdClass();
        $regionDatamodel = $config->datamodel;
        $oldStyle = [];
        foreach (self::LEGACY_SIMULATIONS as $name) {
            if (ConfigValues::has($regionDatamodel, $name)) {
                $oldStyle[$name] = $regionDatamodel->{$name};
            }
        }

        $genericLayers = [];
        foreach (is_array($genericDatamodel->meta ?? null) ? $genericDatamodel->meta : [] as $layer) {
            $genericLayers[$layer->msp_config_generic_name] = $layer;
        }
        $layers = [];
        $present = [];      // generic name => true
        $layerNameOf = [];  // generic name => region layer name
        foreach (is_array($regionDatamodel->meta ?? null) ? $regionDatamodel->meta : [] as $entry) {
            $name = $entry->msp_config_generic_name ?? null;
            $layers[] = $this->mergeLayer($name === null ? null : ($genericLayers[$name] ?? null), $entry);
            if ($name !== null) {
                $present[$name] = true;
                $layerNameOf[$name] = (string)($entry->layer_name ?? $name);
            } elseif (isset($entry->layer_name)) {
                // a layer without a generic counterpart: sections of the region refer to it by its layer name
                $layerNameOf[(string)$entry->layer_name] = (string)$entry->layer_name;
            }
        }
        $back = function (string $reference) use ($layerNameOf, &$warnings): string {
            $parts = explode('|', $reference, 2);
            $base = $parts[0];
            if (!isset($layerNameOf[$base])) {
                $message = "Layer reference \"$reference\" is not a layer of this config, kept as is.";
                if (!in_array($message, $warnings, true)) {
                    $warnings[] = $message;
                }
                return $reference;
            }
            return isset($parts[1]) ? $layerNameOf[$base] . '|' . $parts[1] : $layerNameOf[$base];
        };

        $result = new \stdClass();
        if (isset($config->metadata)) {
            $result->metadata = ConfigValues::clone($config->metadata);
            if ($result->metadata instanceof \stdClass) {
                unset($result->metadata->parent); // it says where the config came from, the final config is complete
            }
        }
        $datamodel = new \stdClass();
        $done = [];
        foreach (ConfigValues::props($regionDatamodel) as $key => $value) {
            switch ($key) {
                case 'meta':
                    $datamodel->meta = $layers;
                    break;
                case 'restrictions':
                    $datamodel->restrictions = $this->mergeRestrictions(
                        $genericDatamodel->restrictions ?? null,
                        $value,
                        $present,
                        $back
                    );
                    $done['restrictions'] = true;
                    break;
                case 'dependencies':
                    $datamodel->dependencies = ConfigValues::clone($value);
                    $done['dependencies'] = true;
                    break;
                case 'CEL':
                case 'REL':
                case 'SEL':
                case 'MEL':
                case 'simulation_settings':
                    // at the place of the first of them; the old-style keys themselves are not in the result
                    if (!isset($done['simulation_settings'])) {
                        $datamodel->simulation_settings = $this->mergeSimulation(
                            $genericDatamodel->simulation_settings ?? null,
                            $regionDatamodel->simulation_settings ?? null,
                            $oldStyle,
                            $present,
                            $back
                        );
                        $done['simulation_settings'] = true;
                    }
                    break;
                default:
                    $datamodel->{$key} = ConfigValues::clone($value);
            }
        }
        if (!isset($done['restrictions'])) {
            // complete configs always have a restrictions object, also when it is empty
            $datamodel->restrictions = $this->mergeRestrictions(
                $genericDatamodel->restrictions ?? null,
                null,
                $present,
                $back
            );
        }
        if (!isset($done['dependencies']) && ConfigValues::has($genericDatamodel, 'dependencies')) {
            $datamodel->dependencies = ConfigValues::clone($genericDatamodel->dependencies);
        }
        if (!isset($done['simulation_settings'])) {
            $datamodel->simulation_settings = $this->mergeSimulation(
                $genericDatamodel->simulation_settings ?? null,
                null,
                [],
                $present,
                $back
            );
        }
        $result->datamodel = $datamodel;
        return $result;
    }

    /**
     * Combines two generic configs: the config of a parent and the generic config that has it as its parent. The
     * result is a generic config again (layers keep their msp_config_generic_name), so it can be merged with a
     * region config, or serve as the parent of another generic config.
     *
     * The layers are a pool: the child adds layers and changes layers of the parent by name, with the same rules as a
     * region has for the layers of a generic config. The sections (restrictions, dependencies, simulation settings)
     * combine as they do for a region, except that all the layers of both are known, so nothing is left out.
     */
    public function mergeGeneric(\stdClass $parent, \stdClass $child): \stdClass
    {
        $parentDatamodel = ($parent->datamodel ?? null) instanceof \stdClass ? $parent->datamodel : new \stdClass();
        $childDatamodel = ($child->datamodel ?? null) instanceof \stdClass ? $child->datamodel : new \stdClass();

        $layers = [];
        foreach (is_array($parentDatamodel->meta ?? null) ? $parentDatamodel->meta : [] as $layer) {
            $layers[$layer->msp_config_generic_name] = ConfigValues::clone($layer);
        }
        foreach (is_array($childDatamodel->meta ?? null) ? $childDatamodel->meta : [] as $layer) {
            $name = $layer->msp_config_generic_name;
            $layers[$name] = isset($layers[$name])
                ? $this->withName($name, $this->mergeLayer($layers[$name], $layer))
                : ConfigValues::clone($layer);
        }
        $present = array_fill_keys(array_map('strval', array_keys($layers)), true);
        $unchanged = static fn(string $reference): string => $reference;

        $result = new \stdClass();
        $metadata = ConfigValues::clone($child->metadata ?? $parent->metadata ?? new \stdClass());
        if ($metadata instanceof \stdClass) {
            unset($metadata->parent);
        }
        $result->metadata = $metadata;
        $datamodel = new \stdClass();
        $datamodel->meta = array_values($layers);
        if (ConfigValues::has($parentDatamodel, 'restrictions') || ConfigValues::has($childDatamodel, 'restrictions')) {
            $datamodel->restrictions = $this->mergeRestrictions(
                $parentDatamodel->restrictions ?? null,
                $childDatamodel->restrictions ?? null,
                $present,
                $unchanged
            );
        }
        foreach ([$childDatamodel, $parentDatamodel] as $source) {
            if (ConfigValues::has($source, 'dependencies')) {
                $datamodel->dependencies = ConfigValues::clone($source->dependencies);
                break;
            }
        }
        if (ConfigValues::has($parentDatamodel, 'simulation_settings')
            || ConfigValues::has($childDatamodel, 'simulation_settings')) {
            $datamodel->simulation_settings = $this->mergeSimulation(
                $parentDatamodel->simulation_settings ?? null,
                $childDatamodel->simulation_settings ?? null,
                [],
                $present,
                $unchanged
            );
        }
        $result->datamodel = $datamodel;
        return $result;
    }

    private function withName(string $name, \stdClass $layer): \stdClass
    {
        $named = new \stdClass();
        $named->msp_config_generic_name = $name;
        foreach (ConfigValues::props($layer) as $key => $value) {
            $named->{$key} = $value;
        }
        return $named;
    }

    /**
     * Objects merge recursively, everything else of $region replaces $generic.
     */
    public static function deepMerge(mixed $generic, mixed $region): mixed
    {
        if ($generic instanceof \stdClass && $region instanceof \stdClass) {
            $merged = ConfigValues::clone($generic);
            foreach (ConfigValues::props($region) as $key => $value) {
                $merged->{$key} = ConfigValues::has($generic, $key)
                    ? self::deepMerge($generic->{$key}, $value)
                    : ConfigValues::clone($value);
            }
            return $merged;
        }
        return ConfigValues::clone($region);
    }

    /**
     * layer_info_properties are keyed by property_name: an item of the region with the name of an item of the generic
     * layer is merged into it (the region wins), the other items of the region come after the generic ones.
     *
     * @param array<int, mixed> $generic
     * @param array<int, mixed> $region
     * @return array<int, mixed>
     */
    private function mergeInfoProperties(array $generic, array $region): array
    {
        $merged = array_values(ConfigValues::clone($generic));
        $index = [];
        foreach ($merged as $position => $item) {
            $name = LayerReferences::propertyName($item);
            if ($name !== null && !isset($index[$name])) {
                $index[$name] = $position;
            }
        }
        foreach ($region as $item) {
            $name = LayerReferences::propertyName($item);
            if ($name !== null && isset($index[$name])) {
                $merged[$index[$name]] = self::deepMerge($merged[$index[$name]], $item);
                unset($index[$name]); // a second item of the region with this name is added, not merged again
            } else {
                $merged[] = ConfigValues::clone($item);
            }
        }
        return $merged;
    }

    private function mergeLayer(?\stdClass $generic, \stdClass $entry): \stdClass
    {
        $entry = ConfigValues::clone($entry);
        unset($entry->msp_config_generic_name);
        if ($generic === null) {
            return $this->orderLayerKeys($entry);
        }
        $generic = ConfigValues::clone($generic);
        unset($generic->msp_config_generic_name);

        $genericHas = ConfigValues::has($generic, 'layer_info_properties');
        $genericProperties = $generic->layer_info_properties ?? null;
        $regionHas = ConfigValues::has($entry, 'layer_info_properties');
        $regionProperties = $entry->layer_info_properties ?? null;
        unset($generic->layer_info_properties, $entry->layer_info_properties);

        $merged = self::deepMerge($generic, $entry);
        if ($regionHas) {
            $merged->layer_info_properties = is_array($genericProperties) && is_array($regionProperties)
                ? $this->mergeInfoProperties($genericProperties, $regionProperties)
                : $regionProperties;
        } elseif ($genericHas) {
            $merged->layer_info_properties = $genericProperties;
        }
        return $this->orderLayerKeys($merged);
    }

    private function orderLayerKeys(\stdClass $layer): \stdClass
    {
        $ordered = new \stdClass();
        foreach (self::LAYER_KEY_ORDER as $key) {
            if (ConfigValues::has($layer, $key)) {
                $ordered->{$key} = $layer->{$key};
            }
        }
        foreach (ConfigValues::props($layer) as $key => $value) {
            if (!ConfigValues::has($ordered, $key)) {
                $ordered->{$key} = $value;
            }
        }
        return $ordered;
    }

    private function mergeRestrictions(mixed $generic, mixed $region, array $present, \Closure $back): \stdClass
    {
        $entries = [];
        $items = [
            [LayerReferences::flattenRestrictions($generic), true],
            [LayerReferences::flattenRestrictions($region), false],
        ];
        foreach ($items as [$list, $isGeneric]) {
            foreach ($list as $entry) {
                if ($isGeneric
                    && (!isset($present[LayerReferences::base((string)$entry->startlayer)])
                        || !isset($present[LayerReferences::base((string)$entry->endlayer)]))) {
                    continue; // the region does not have one of the layers
                }
                $copy = ConfigValues::clone($entry);
                $copy->startlayer = $back((string)($entry->startlayer ?? ''));
                $copy->endlayer = $back((string)($entry->endlayer ?? ''));
                $entries[] = $copy;
            }
        }
        return LayerReferences::groupRestrictions($entries);
    }

    /**
     * The effective simulation settings: CEL, SEL and MEL, each null when neither the generic config, the region
     * config nor an old-style key has it. A region that has an explicit null does not inherit the generic one.
     *
     * @param array<string, mixed> $oldStyle old-style CEL/SEL/MEL keys of the config (name => value)
     */
    private function mergeSimulation(
        mixed $generic,
        mixed $region,
        array $oldStyle,
        array $present,
        \Closure $back
    ): \stdClass {
        $settings = new \stdClass();
        // every simulation there is: the ones that are always there, and every key of the generic and region part
        $names = array_merge(
            self::SIMULATIONS,
            array_keys($generic instanceof \stdClass ? ConfigValues::props($generic) : []),
            array_keys($region instanceof \stdClass ? ConfigValues::props($region) : []),
            array_keys($oldStyle)
        );
        foreach (array_unique(array_map('strval', $names)) as $name) {
            $genericPart = $generic instanceof \stdClass && ConfigValues::has($generic, $name)
                ? $generic->{$name} : null;
            $regionHas = $region instanceof \stdClass && ConfigValues::has($region, $name);
            $regionPart = $regionHas ? $region->{$name} : null;
            if ($regionHas && $regionPart === null) {
                $merged = null; // explicitly none: the generic one is not inherited
            } elseif (!$regionHas) {
                $merged = ConfigValues::clone($genericPart);
                if ($name === 'SEL' && $merged instanceof \stdClass) {
                    $this->filterAdditiveSel($merged, $present);
                }
            } elseif ($genericPart instanceof \stdClass && $regionPart instanceof \stdClass) {
                $merged = $name === 'SEL'
                    ? $this->mergeSel($genericPart, $regionPart, $present)
                    : self::deepMerge($genericPart, $regionPart);
            } else {
                $merged = ConfigValues::clone($regionPart); // nothing to merge it with: it replaces
            }
            if ($name === 'SEL' && $merged instanceof \stdClass) {
                // the generic and region part use generic names: back to layer names. The old-style SEL has them
                // already, and replaces what it has: do not translate (and warn about) what it replaces anyway
                if (($oldStyle['SEL'] ?? null) instanceof \stdClass) {
                    $merged = self::withoutOverridden($merged, $oldStyle['SEL']);
                }
                LayerReferences::mapSel($merged, $back);
            }
            if (array_key_exists($name, $oldStyle)) {
                $merged = self::layOldStyleOver($merged, $oldStyle[$name]);
            }
            $settings->{$name} = $merged;
        }
        return $settings;
    }

    /**
     * $base without the values that deepMerge($base, $overlay) replaces anyway.
     */
    private static function withoutOverridden(\stdClass $base, \stdClass $overlay): \stdClass
    {
        $pruned = ConfigValues::clone($base);
        foreach (array_keys(ConfigValues::props($overlay)) as $key) {
            $key = (string)$key;
            if (!ConfigValues::has($pruned, $key)) {
                continue;
            }
            if ($pruned->{$key} instanceof \stdClass && $overlay->{$key} instanceof \stdClass) {
                $pruned->{$key} = self::withoutOverridden($pruned->{$key}, $overlay->{$key});
            } else {
                unset($pruned->{$key});
            }
        }
        return $pruned;
    }

    /**
     * An old-style CEL/SEL/MEL laid on top of what the other layers give: key by key the old-style value wins (a
     * list replaces the other list), null means no simulation.
     */
    private static function layOldStyleOver(mixed $base, mixed $oldStyle): mixed
    {
        if ($oldStyle === null) {
            return null;
        }
        return $base instanceof \stdClass && $oldStyle instanceof \stdClass
            ? self::deepMerge($base, $oldStyle)
            : ConfigValues::clone($oldStyle);
    }

    /**
     * Moves CEL, SEL and MEL from datamodel (editor format) into datamodel.simulation_settings, at the
     * place of the first of them. An old-style key wins over the same key in an existing simulation_settings,
     * key by key. Only the keys the config has are moved; nothing is invented.
     */
    public static function toSimulationSettings(\stdClass $config): \stdClass
    {
        $config = ConfigValues::clone($config);
        $datamodel = $config->datamodel ?? null;
        if (!$datamodel instanceof \stdClass) {
            return $config;
        }
        $legacy = array_filter(
            self::LEGACY_SIMULATIONS,
            static fn(string $name) => ConfigValues::has($datamodel, $name)
        );
        if ($legacy === []) {
            return $config;
        }
        $settings = ($datamodel->simulation_settings ?? null) instanceof \stdClass
            ? $datamodel->simulation_settings : new \stdClass();
        $rebuilt = new \stdClass();
        $placed = ConfigValues::has($datamodel, 'simulation_settings');
        foreach (ConfigValues::props($datamodel) as $key => $value) {
            if ($key === 'simulation_settings') {
                $rebuilt->simulation_settings = $settings;
            } elseif (in_array($key, self::LEGACY_SIMULATIONS, true)) {
                $settings->{$key} = ConfigValues::has($settings, $key)
                    ? self::layOldStyleOver($settings->{$key}, $value)
                    : $value;
                if (!$placed) {
                    $rebuilt->simulation_settings = $settings;
                    $placed = true;
                }
            } else {
                $rebuilt->{$key} = $value;
            }
        }
        $config->datamodel = $rebuilt;
        return $config;
    }

    private function mergeSel(\stdClass $generic, \stdClass $region, array $present): \stdClass
    {
        $merged = ConfigValues::clone($generic);
        $this->filterAdditiveSel($merged, $present);
        foreach (ConfigValues::props($region) as $key => $value) {
            if (in_array($key, ConfigSplitter::SEL_ADDITIVE, true)
                && is_array($merged->{$key} ?? null) && is_array($value)) {
                $merged->{$key} = array_merge($merged->{$key}, ConfigValues::clone($value));
            } elseif (ConfigValues::has($merged, $key)) {
                $merged->{$key} = self::deepMerge($merged->{$key}, $value);
            } else {
                $merged->{$key} = ConfigValues::clone($value);
            }
        }
        return $merged;
    }

    private function filterAdditiveSel(\stdClass $sel, array $present): void
    {
        foreach (ConfigSplitter::SEL_ADDITIVE as $key) {
            if (is_array($sel->{$key} ?? null)) {
                $sel->{$key} = LayerReferences::filterPresent($sel->{$key}, $present);
            }
        }
    }
}
