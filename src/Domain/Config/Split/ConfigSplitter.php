<?php

namespace App\Domain\Config\Split;

use App\Domain\Config\Merge\LayerReferences;
use App\Domain\Config\Merge\RegionConfigMerger;

/**
 * Splits full MSP Challenge region configs into ONE generic config plus ONE region config per input,
 * following "Config simplification overview" (Hutchinson, 2025-07-02), with the differences that are in differences.md.
 *
 * Merge semantics the output is designed for (implemented by RegionConfigMerger):
 *  - objects merge recursively, region wins;
 *  - arrays and scalars in the region replace the generic value;
 *  - restrictions and the lists of SEL (shipping_lane_layers, port_layers, restriction_layer_exceptions) are lists to
 *    which the region ADDS items;
 *  - layer_info_properties are keyed by property_name: a region item with the name of a generic item is merged into it;
 *  - `dependencies` is replaced as a whole;
 *  - an explicit null in the region replaces the generic value (used for CEL/SEL/MEL of regions without them);
 *  - simulation_settings is open-ended: every key of it is a simulation. CEL, SEL and MEL are always there (they live
 *    in `simulation_settings`, like `policy_settings`), in both input and output;
 *  - `meta` is a list of region layer entries, each pointing at a generic layer through
 *    msp_config_generic_name; the region list defines which layers exist and in which order.
 * Data is generic as soon as 2 or more regions share it, and a region never has to delete anything, because a region
 * can not take away what is generic: objects: keys present in all; values: the most common value (sections need
 * support from >= 2 regions); additive lists (restrictions, SEL lists): an item that refers to layers is generic when
 * every config that has those layers has it and 2 or more configs have them; layer_info_properties: a property that
 * every config has, of which 2 or more share a version. So the split is lossless by construction, apart from the
 * documented removals.
 */
final class ConfigSplitter
{
    /** Removed from every layer by the design document. */
    public const array REMOVED_LAYER_KEYS = [
        'layer_raster_filter_mode',
        'layer_information',
    ];

    /**
     * Removed from the layers that are not raster layers. A raster layer keeps them: the server needs its
     * layer_height to download the raster from GeoServer (the width follows from it), and the sizes differ per layer.
     */
    public const array REMOVED_NON_RASTER_LAYER_KEYS = [
        'layer_width',
        'layer_height',
    ];

    /**
     * The keys that are removed from this layer.
     *
     * @return string[]
     */
    public static function removedLayerKeys(\stdClass $layer): array
    {
        return ($layer->layer_geotype ?? null) === 'raster'
            ? self::REMOVED_LAYER_KEYS
            : array_merge(self::REMOVED_LAYER_KEYS, self::REMOVED_NON_RASTER_LAYER_KEYS);
    }

    /** Always kept in the region layer entry. */
    public const array REGION_LAYER_KEYS = [
        'layer_name',
        'layer_download_from_geoserver',
        'layer_raster_minimum_value_cutoff',
    ];

    public const array SEL_REGION_ONLY = ['port_intensity'];

    public const array SEL_ADDITIVE = ['shipping_lane_layers', 'port_layers', 'restriction_layer_exceptions'];

    public const array SEL_SHARED = [
        'country_border_layer',
        'shipping_lane_point_merge_distance',
        'shipping_lane_subdivide_distance',
        'shipping_lane_implicit_distance_limit',
        'maintenance_destinations',
        'output_configuration',
        'risk_heatmap_settings',
        'heatmap_settings',
        'heatmap_bleed_config',
        'ship_types',
        'layer_type_ship_type_mapping',
        'shipping_kpi_config',
    ];

    /** A value in a section only becomes generic when at least this many regions agree on it. */
    private const int SECTION_MIN_SUPPORT = 2;

    /** @var array<string, array{of: int, best: int, distinct: int, generic: bool, kind: string}> */
    private array $support = [];
    /** @var array<string, int> */
    private array $dropped = [];
    /** @var array<string, int> */
    private array $layerOverrides = [];
    /** @var array<string, true> */
    private array $unresolved = [];
    /** @var array<string, true> generic names of the layers that are used by 2 or more configs */
    private array $shared = [];
    /** @var array<string, array<string, true>> config id => the generic names of the shared layers it has */
    private array $layersOf = [];
    private int $regionOnlyLayers = 0;
    /** @var string[] */
    private array $warnings = [];

    public function __construct(private readonly GenericNameRegistry $names)
    {
    }

    /**
     * @param array<string, \stdClass> $configs config id => decoded config (root object: metadata + datamodel)
     */
    public function split(array $configs): SplitResult
    {
        $this->resetState();
        foreach ($configs as $id => $root) {
            if (!($root->datamodel ?? null) instanceof \stdClass) {
                throw new \InvalidArgumentException("Config \"$id\" has no datamodel object.");
            }
        }

        [$genericMeta, $regionMeta, $sharedLayers] = $this->splitLayers($configs);
        [$genericRestrictions, $regionRestrictions] = $this->splitRestrictions($configs);
        [$genericDependencies, $regionDependencies] = $this->splitDependencies($configs);
        [$genericSimulation, $regionSimulation] = $this->splitSimulationSettings($configs);

        $genericDatamodel = new \stdClass();
        $genericDatamodel->meta = $genericMeta;
        if ($genericRestrictions !== null) {
            $genericDatamodel->restrictions = $genericRestrictions;
        }
        if ($genericDependencies !== null) {
            $genericDatamodel->dependencies = $genericDependencies;
        }
        if ($genericSimulation !== null) {
            $genericDatamodel->simulation_settings = $genericSimulation;
        }
        $generic = new \stdClass();
        $generic->metadata = $this->genericMetadata($configs);
        $generic->datamodel = $genericDatamodel;
        // which layer names belong to which generic layer: authoring information, it is not used when merging, but it
        // travels with the generic config so a restricted generic config never has to put its names in a shared file
        $generic->layer_names = (object)$this->names->toMap();

        $regions = [];
        foreach ($configs as $id => $root) {
            $regions[$id] = $this->buildRegionDocument(
                $root,
                $regionMeta[$id],
                $regionRestrictions[$id] ?? null,
                array_key_exists($id, $regionDependencies),
                $regionDependencies[$id] ?? null,
                $regionSimulation[$id] ?? null
            );
        }

        foreach ($this->unresolvedWarnings() as $warning) {
            $this->warnings[] = $warning;
        }

        return new SplitResult(
            $generic,
            $regions,
            count($genericMeta),
            $sharedLayers,
            $this->layerOverrides,
            $this->support,
            $this->dropped,
            $this->warnings,
            $this->regionOnlyLayers
        );
    }

    // ------------------------------------------------------------------------------------------------
    // layers ("meta")
    // ------------------------------------------------------------------------------------------------

    /**
     * @param array<string, \stdClass> $configs
     * @return array{0: \stdClass[], 1: array<string, \stdClass[]>, 2: int}
     */
    private function splitLayers(array $configs): array
    {
        /** @var array<string, array<string, array{base: \stdClass, props: array}>> $clusters */
        $clusters = [];
        foreach ($configs as $id => $root) {
            foreach ($root->datamodel->meta ?? [] as $layer) {
                $generic = $this->names->get($layer->layer_name)
                    ?? throw new \RuntimeException("No generic name for layer \"$layer->layer_name\" ($id).");
                if (isset($clusters[$generic][$id])) {
                    throw new \RuntimeException("Config \"$id\" has two layers with generic name \"$generic\".");
                }
                $base = new \stdClass();
                $removed = self::removedLayerKeys($layer);
                foreach (ConfigValues::props($layer) as $key => $value) {
                    if (in_array($key, $removed, true)) {
                        $this->countRemovedLayerKey($key, $value);
                        continue;
                    }
                    if (in_array($key, self::REGION_LAYER_KEYS, true) || $key === 'layer_info_properties') {
                        continue;
                    }
                    $base->{$key} = ConfigValues::clone($value);
                }
                // null and [] are different values in the existing configs (300 layers have null), keep them apart
                $clusters[$generic][$id] = ['base' => $base, 'props' => $layer->layer_info_properties ?? null];
            }
        }

        $genericMeta = [];
        $patches = [];
        $propertyPatches = [];
        $shared = 0;
        foreach ($clusters as $generic => $byConfig) {
            $generic = (string)$generic;
            if (count($byConfig) === 1) {
                // used by one config only: nothing is shared, the layer lives in that config's file
                $this->regionOnlyLayers++;
                continue;
            }
            $this->shared[$generic] = true;
            foreach (array_keys($byConfig) as $id) {
                $this->layersOf[$id][$generic] = true;
            }
            $shared++;
            $bases = array_map(static fn(array $entry) => $entry['base'], $byConfig);
            $properties = array_map(static fn(array $entry) => $entry['props'], $byConfig);

            [$has, $genericBase, $basePatches] = $this->splitValue($bases, 1, 'meta.' . $generic);

            // layer_info_properties: the region ADDS to the generic list, but only when both are lists.
            // If any config has null, the generic value is null and a region list simply replaces it.
            $setGenericProperties = false;
            $genericProperties = null;
            $emitProperties = []; // config id => list the region entry carries
            if (array_reduce($properties, static fn(bool $all, $list) => $all && is_array($list), true)) {
                [$hasProperties, $genericList, $propPatches] = $this->splitInfoProperties($properties);
                $setGenericProperties = $hasProperties;
                $genericProperties = $genericList;
                foreach (array_keys($byConfig) as $id) {
                    if (!$hasProperties) {
                        $emitProperties[$id] = $properties[$id]; // no generic list: the region carries its own
                    } elseif (($propPatches[$id] ?? []) !== []) {
                        $emitProperties[$id] = $propPatches[$id];
                    }
                }
            } else {
                $setGenericProperties = true; // explicit null
                foreach (array_keys($byConfig) as $id) {
                    if (is_array($properties[$id])) {
                        $emitProperties[$id] = $properties[$id];
                    }
                }
            }

            $layer = new \stdClass();
            $layer->msp_config_generic_name = $generic;
            if ($has) {
                foreach (ConfigValues::props($genericBase) as $key => $value) {
                    $layer->{$key} = $value;
                }
            }
            if ($setGenericProperties) {
                $layer->layer_info_properties = $genericProperties;
            }
            $genericMeta[] = $layer;

            foreach (array_keys($byConfig) as $id) {
                $patch = $has ? ($basePatches[$id] ?? new \stdClass()) : $bases[$id];
                foreach (array_keys(ConfigValues::props($patch)) as $key) {
                    $this->layerOverrides[$key] = ($this->layerOverrides[$key] ?? 0) + 1;
                }
                $patches[$generic][$id] = $patch;
                if (array_key_exists($id, $emitProperties)) {
                    $propertyPatches[$generic][$id] = $emitProperties[$id];
                    if ($emitProperties[$id] !== []) {
                        $this->layerOverrides['layer_info_properties'] =
                            ($this->layerOverrides['layer_info_properties'] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($this->layerOverrides);

        $regionMeta = [];
        foreach ($configs as $id => $root) {
            $entries = [];
            $sharedHere = [];
            foreach ($root->datamodel->meta ?? [] as $layer) {
                $sharedHere[(string)$this->names->get($layer->layer_name)] = true;
            }
            foreach ($root->datamodel->meta ?? [] as $layer) {
                $generic = (string)$this->names->get($layer->layer_name);
                if (!isset($this->shared[$generic])) {
                    if (isset($sharedHere[$layer->layer_name]) && isset($this->shared[$layer->layer_name])) {
                        throw new \RuntimeException(sprintf(
                            'Config "%s": layer "%s" is only used by this config, but "%s" is also the generic name '
                            . 'of a layer it uses. Rename one in the layer name map.',
                            $id,
                            $layer->layer_name,
                            $layer->layer_name
                        ));
                    }
                    $entries[] = $this->regionOnlyLayer($layer);
                    continue;
                }
                $entry = new \stdClass();
                $entry->msp_config_generic_name = $generic;
                foreach (self::REGION_LAYER_KEYS as $key) {
                    if (ConfigValues::has($layer, $key)) {
                        $entry->{$key} = ConfigValues::clone($layer->{$key});
                    }
                }
                foreach (ConfigValues::props($patches[$generic][$id]) as $key => $value) {
                    $entry->{$key} = $value;
                }
                if (array_key_exists($id, $propertyPatches[$generic] ?? [])) {
                    $entry->layer_info_properties = $propertyPatches[$generic][$id];
                }
                $entries[] = $entry;
            }
            $regionMeta[$id] = $entries;
        }

        return [$genericMeta, $regionMeta, $shared];
    }

    /**
     * A layer that no other config has: complete, without msp_config_generic_name (there is no generic layer to
     * match). Sections that refer to it use its layer_name.
     */
    private function regionOnlyLayer(\stdClass $layer): \stdClass
    {
        $entry = new \stdClass();
        $removed = self::removedLayerKeys($layer);
        foreach (ConfigValues::props($layer) as $key => $value) {
            if (!in_array($key, $removed, true)) {
                $entry->{$key} = ConfigValues::clone($value);
            }
        }
        return $entry;
    }

    private function resetState(): void
    {
        $this->support = [];
        $this->dropped = [];
        $this->layerOverrides = [];
        $this->unresolved = [];
        $this->warnings = [];
        $this->shared = [];
        $this->layersOf = [];
        $this->regionOnlyLayers = 0;
    }

    /**
     * @return string[]
     */
    private function unresolvedWarnings(): array
    {
        return array_map(
            static fn(string $item) => 'Unresolved layer reference (not a layer of that config, kept as is): ' . $item,
            array_keys($this->unresolved)
        );
    }

    private function countRemovedLayerKey(string $key, mixed $value): void
    {
        // counted: every layer for width/height (once per layer), otherwise only a value that is not the default
        [$label, $counts] = match ($key) {
            'layer_width', 'layer_height' => [
                'layer_width / layer_height of layers that are not raster layers',
                $key === 'layer_width',
            ],
            'layer_raster_filter_mode' => ['layer_raster_filter_mode with a value other than 1', $value !== 1],
            'layer_information' => ['layer_information with a non-empty value', $value !== '' && $value !== null],
            default => [$key, false],
        };
        if ($counts) {
            $this->dropped[$label] = ($this->dropped[$label] ?? 0) + 1;
        }
    }

    // ------------------------------------------------------------------------------------------------
    // restrictions
    // ------------------------------------------------------------------------------------------------

    /**
     * @param array<string, \stdClass> $configs
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitRestrictions(array $configs): array
    {
        $lists = [];
        foreach ($configs as $id => $root) {
            $entries = [];
            $restrictions = $root->datamodel->restrictions ?? null;
            foreach ($restrictions instanceof \stdClass ? ConfigValues::props($restrictions) : [] as $key => $list) {
                foreach ($list as $entry) {
                    $copy = ConfigValues::clone($entry);
                    if ($key !== ($entry->startlayer ?? '') . '|' . ($entry->endlayer ?? '')) {
                        $label = 'restriction map keys rewritten (did not match startlayer|endlayer)';
                        $this->dropped[$label] = ($this->dropped[$label] ?? 0) + 1;
                    }
                    $copy->startlayer = $this->translate((string)($entry->startlayer ?? ''), $id, 'restrictions');
                    $copy->endlayer = $this->translate((string)($entry->endlayer ?? ''), $id, 'restrictions');
                    $entries[] = $copy;
                }
            }
            $lists[$id] = $entries;
        }

        [$has, $generic, $patches] = $this->splitAdditiveByLayers($lists, 'restrictions', 'restrictions');
        $regionRestrictions = [];
        foreach ($patches as $id => $entries) {
            if ($entries !== []) {
                $regionRestrictions[$id] = $this->groupRestrictions($entries);
            }
        }
        return [$has ? $this->groupRestrictions($generic) : null, $regionRestrictions];
    }

    /**
     * @param \stdClass[] $entries
     */
    private function groupRestrictions(array $entries): \stdClass
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry->startlayer . '|' . $entry->endlayer][] = $entry;
        }
        $object = new \stdClass();
        foreach ($groups as $key => $list) {
            $object->{(string)$key} = $list;
        }
        return $object;
    }

    // ------------------------------------------------------------------------------------------------
    // dependencies
    // ------------------------------------------------------------------------------------------------

    /**
     * Dependencies are generic but a region replaces them as a whole; no combination is done.
     *
     * @param array<string, \stdClass> $configs
     * @return array{0: ?\stdClass, 1: array<string, mixed>} generic, and per config the value to emit
     *         (the key is absent when the region just uses the generic one)
     */
    private function splitDependencies(array $configs): array
    {
        $values = [];
        foreach ($configs as $id => $root) {
            $dependencies = $root->datamodel->dependencies ?? null;
            if ($dependencies instanceof \stdClass) {
                $values[$id] = $dependencies;
            }
        }
        if ($values === []) {
            return [null, []];
        }
        [$has, $generic, $patches] = $this->splitValue($values, self::SECTION_MIN_SUPPORT, 'dependencies', true);
        $emit = [];
        foreach ($configs as $id => $_) {
            if (!isset($values[$id])) {
                if ($has) {
                    $emit[$id] = null;
                }
            } elseif (array_key_exists($id, $patches)) {
                $emit[$id] = $patches[$id];
            }
        }
        return [$has ? $generic : null, $emit];
    }

    // ------------------------------------------------------------------------------------------------
    // simulation settings (CEL, SEL, MEL)
    // ------------------------------------------------------------------------------------------------

    /**
     * CEL, SEL and MEL are the simulation settings. The input may have them top-level in datamodel (the
     * editor format) or already inside `simulation_settings`.
     *
     * @param array<string, \stdClass> $configs
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitSimulationSettings(array $configs): array
    {
        // the simulations of the configs, by name: every key of simulation_settings, and the old-style ones that a
        // config has directly in datamodel
        $objects = []; // name => config id => the settings (SEL with its layer references translated)
        $others = [];  // name => config id => null or something that is no object, kept as it is
        foreach ($configs as $id => $root) {
            foreach ($this->simulationNames($root->datamodel) as $name) {
                $value = $this->simulationPart($root->datamodel, $name);
                if ($value instanceof \stdClass) {
                    $objects[$name][$id] = $name === 'SEL'
                        ? $this->translateSel(ConfigValues::clone($value), $id)
                        : $value;
                } elseif (!in_array($name, RegionConfigMerger::SIMULATIONS, true)) {
                    $others[$name][$id] = $value;
                }
            }
        }
        $parts = [];
        foreach (array_unique(array_merge(
            RegionConfigMerger::SIMULATIONS,
            array_keys($objects),
            array_keys($others)
        )) as $name) {
            $name = (string)$name;
            $values = $objects[$name] ?? [];
            $parts[$name] = [$values, ...match ($name) {
                'CEL' => $this->splitCel($values),
                'SEL' => $this->splitSel($values),
                'MEL' => $this->splitMel($values),
                // settings of other simulations: generic where the configs share them, like the settings of CEL
                default => $this->splitOtherSimulation($values, $name, count($configs)),
            }];
        }

        $generic = new \stdClass();
        foreach ($parts as $name => [, $genericPart]) {
            if ($genericPart !== null) {
                $generic->{$name} = $genericPart;
            }
        }

        $regionSimulation = [];
        foreach ($configs as $id => $_) {
            $simulation = new \stdClass();
            foreach ($parts as $name => [$present, $genericPart, $patchesPart]) {
                if (isset($present[$id])) {
                    if (ConfigValues::props($patchesPart[$id] ?? new \stdClass()) !== []) {
                        $simulation->{$name} = $patchesPart[$id];
                    }
                } elseif (array_key_exists($id, $others[$name] ?? [])) {
                    $simulation->{$name} = ConfigValues::clone($others[$name][$id]); // as it is: none, or no object
                } elseif ($genericPart !== null) {
                    $simulation->{$name} = null; // this region has none: do not inherit the generic one
                }
            }
            if (ConfigValues::props($simulation) !== []) {
                $regionSimulation[$id] = $simulation;
            }
        }
        return [ConfigValues::props($generic) === [] ? null : $generic, $regionSimulation];
    }

    /**
     * The names of the simulations of a config: every key of simulation_settings, and the old-style ones directly in
     * datamodel.
     *
     * @return string[]
     */
    private function simulationNames(\stdClass $datamodel): array
    {
        $settings = $datamodel->simulation_settings ?? null;
        $names = array_map('strval', array_keys($settings instanceof \stdClass ? ConfigValues::props($settings) : []));
        foreach (RegionConfigMerger::LEGACY_SIMULATIONS as $name) {
            if (ConfigValues::has($datamodel, $name)) {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    private function simulationPart(\stdClass $datamodel, string $name): mixed
    {
        $settings = $datamodel->simulation_settings ?? null;
        if ($settings instanceof \stdClass && ConfigValues::has($settings, $name)) {
            return $settings->{$name};
        }
        return $datamodel->{$name} ?? null;
    }

    /**
     * The settings of a simulation that has no rules of its own: generic where 2 or more configs share a value, the
     * rest in the regions. A region can not say that it does not have a simulation that is generic, except by the
     * explicit null that is only written for the simulations CEL, SEL and MEL, so the settings only become generic
     * when every config has them.
     *
     * @param array<string, \stdClass> $values
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitOtherSimulation(array $values, string $name, int $configCount): array
    {
        if ($values === []) {
            return [null, []];
        }
        if (count($values) !== $configCount) {
            return [null, array_map(ConfigValues::clone(...), $values)];
        }
        [$has, $generic, $patches] = $this->splitValue($values, self::SECTION_MIN_SUPPORT, $name);
        return [$has ? $generic : null, $patches];
    }

    /**
     * CEL (centerpoint colors and sprites) is generic with region overrides.
     *
     * @param array<string, \stdClass> $cels
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitCel(array $cels): array
    {
        if ($cels === []) {
            return [null, []];
        }
        [$has, $generic, $patches] = $this->splitValue($cels, self::SECTION_MIN_SUPPORT, 'CEL');
        return [$has ? $generic : null, $patches];
    }

    /**
     * @param array<string, \stdClass> $sels translated SEL objects of the configs that have one
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitSel(array $sels): array
    {
        if ($sels === []) {
            return [null, []];
        }
        $ids = array_keys($sels);
        $union = [];
        foreach ($sels as $sel) {
            foreach (array_keys(ConfigValues::props($sel)) as $key) {
                $union[$key] = true;
            }
        }
        $generic = new \stdClass();
        $patches = array_fill_keys($ids, null);
        foreach ($patches as $id => $_) {
            $patches[$id] = new \stdClass();
        }
        $known = array_merge(self::SEL_REGION_ONLY, self::SEL_ADDITIVE, self::SEL_SHARED);
        foreach (array_keys($union) as $key) {
            $key = (string)$key;
            $values = [];
            foreach ($sels as $id => $sel) {
                if (ConfigValues::has($sel, $key)) {
                    $values[$id] = $sel->{$key};
                }
            }
            if (!in_array($key, $known, true)) {
                $this->warnings[] = "SEL.$key is not covered by the design document; kept region specific.";
            }
            if (!in_array($key, $known, true) || in_array($key, self::SEL_REGION_ONLY, true)
                || count($values) !== count($ids)) {
                foreach ($values as $id => $value) {
                    $patches[$id]->{$key} = ConfigValues::clone($value);
                }
                continue;
            }
            [$has, $genericValue, $valuePatches] = in_array($key, self::SEL_ADDITIVE, true)
                ? $this->splitAdditiveByLayers($values, 'SEL.' . $key, $key)
                : $this->splitValue($values, self::SECTION_MIN_SUPPORT, 'SEL.' . $key);
            if ($has) {
                $generic->{$key} = $genericValue;
            }
            foreach ($valuePatches as $id => $patch) {
                $patches[$id]->{$key} = $patch;
            }
        }
        return [ConfigValues::props($generic) === [] ? null : $generic, $patches];
    }

    /**
     * MEL is region specific, except the KPI categories (ecologyCategories) which may be generic.
     * "valueDefinitions" is handled by the client and removed.
     *
     * @param array<string, \stdClass> $mels
     * @return array{0: ?\stdClass, 1: array<string, \stdClass>}
     */
    private function splitMel(array $mels): array
    {
        if ($mels === []) {
            return [null, []];
        }
        $categories = [];
        foreach ($mels as $id => $mel) {
            if (is_array($mel->ecologyCategories ?? null)) {
                $categories[$id] = $this->stripValueDefinitions($mel->ecologyCategories);
            }
        }
        $genericCategories = null;
        $categoryPatches = [];
        if ($categories !== [] && count($categories) === count($mels)) {
            [$has, $genericValue, $categoryPatches] = $this->splitValue(
                $categories,
                self::SECTION_MIN_SUPPORT,
                'MEL.ecologyCategories'
            );
            $genericCategories = $has ? $genericValue : null;
        }

        $patches = [];
        foreach ($mels as $id => $mel) {
            $patch = new \stdClass();
            foreach (ConfigValues::props($mel) as $key => $value) {
                if ($key !== 'ecologyCategories' || !isset($categories[$id])) {
                    $patch->{$key} = ConfigValues::clone($value);
                } elseif ($genericCategories === null) {
                    $patch->{$key} = $categories[$id];
                } elseif (array_key_exists($id, $categoryPatches)) {
                    $patch->{$key} = $categoryPatches[$id];
                }
            }
            $patches[$id] = $patch;
        }
        $generic = null;
        if ($genericCategories !== null) {
            $generic = new \stdClass();
            $generic->ecologyCategories = $genericCategories;
        }
        return [$generic, $patches];
    }

    /**
     * @param array $categories
     * @return array
     */
    private function stripValueDefinitions(array $categories): array
    {
        $stripped = [];
        foreach ($categories as $category) {
            $copy = ConfigValues::clone($category);
            if ($copy instanceof \stdClass && ConfigValues::has($copy, 'valueDefinitions')) {
                unset($copy->valueDefinitions);
                $label = 'MEL ecologyCategories[].valueDefinitions';
                $this->dropped[$label] = ($this->dropped[$label] ?? 0) + 1;
            }
            $stripped[] = $copy;
        }
        return $stripped;
    }

    /**
     * Layer references in SEL become generic names. Region specific layer names stay in sections that
     * are region specific (MEL, expertise_definitions, ...).
     */
    private function translateSel(\stdClass $sel, string $id): \stdClass
    {
        $where = 'SEL';
        if (is_array($sel->shipping_lane_layers ?? null)) {
            $sel->shipping_lane_layers = array_map(
                fn($name) => $this->translate((string)$name, $id, $where),
                $sel->shipping_lane_layers
            );
        }
        if (is_string($sel->country_border_layer ?? null)) {
            $sel->country_border_layer = $this->translate($sel->country_border_layer, $id, $where);
        }
        foreach (['port_layers', 'restriction_layer_exceptions', 'heatmap_settings'] as $key) {
            foreach (is_array($sel->{$key} ?? null) ? $sel->{$key} : [] as $item) {
                if ($item instanceof \stdClass && is_string($item->layer_name ?? null)) {
                    $item->layer_name = $this->translate($item->layer_name, $id, $where);
                }
            }
        }
        $risk = $sel->risk_heatmap_settings ?? null;
        if ($risk instanceof \stdClass && is_array($risk->restriction_layer_exceptions ?? null)) {
            $risk->restriction_layer_exceptions = array_map(
                fn($name) => $this->translate((string)$name, $id, $where),
                $risk->restriction_layer_exceptions
            );
        }
        return $sel;
    }

    private function translate(string $reference, string $configId, string $where): string
    {
        $generic = $this->names->resolveReference($reference);
        if ($generic === null) {
            $this->unresolved["\"$reference\" in $configId ($where)"] = true;
            return $reference;
        }
        // only layers that exist in the generic config are referred to by their generic name
        return isset($this->shared[explode('|', $generic, 2)[0]]) ? $generic : $reference;
    }

    // ------------------------------------------------------------------------------------------------
    // generic building blocks
    // ------------------------------------------------------------------------------------------------

    /**
     * Splits the values the configs have for the same path into a generic value plus per-config patches.
     *
     * Contract: when the first result is true, merging the generic value with patches[id] (objects merge
     * recursively, anything else is replaced) gives values[id] for every id; a config without a patch
     * simply uses the generic value. When it is false there is no generic value and patches[id] is the
     * complete value of every id.
     *
     * @param array<string, mixed> $values config id => value (never missing for an id)
     * @return array{0: bool, 1: mixed, 2: array<string, mixed>}
     */
    private function splitValue(array $values, int $minSupport, string $path, bool $atomic = false): array
    {
        $allObjects = !$atomic;
        foreach ($values as $value) {
            $allObjects = $allObjects && $value instanceof \stdClass && ConfigValues::props($value) !== [];
        }
        return $allObjects
            ? $this->splitObjects($values, $minSupport, $path)
            : $this->splitLeaf($values, $minSupport, $path);
    }

    /**
     * @param array<string, \stdClass> $values
     * @return array{0: bool, 1: mixed, 2: array<string, mixed>}
     */
    private function splitObjects(array $values, int $minSupport, string $path): array
    {
        $ids = array_keys($values);
        $props = [];
        $union = [];
        foreach ($values as $id => $object) {
            $props[$id] = ConfigValues::props($object);
            foreach (array_keys($props[$id]) as $key) {
                $union[$key] = true;
            }
        }
        $generic = new \stdClass();
        $patches = [];
        foreach (array_keys($union) as $key) {
            $key = (string)$key;
            $have = array_values(array_filter($ids, static fn($id) => array_key_exists($key, $props[$id])));
            if (count($have) !== count($ids)) {
                foreach ($have as $id) {
                    $patches[$id] ??= new \stdClass();
                    $patches[$id]->{$key} = ConfigValues::clone($props[$id][$key]);
                }
                continue;
            }
            $children = [];
            foreach ($ids as $id) {
                $children[$id] = $props[$id][$key];
            }
            [$has, $child, $childPatches] = $this->splitValue($children, $minSupport, $path . '.' . $key);
            if ($has) {
                $generic->{$key} = $child;
            }
            foreach ($childPatches as $id => $patch) {
                $patches[$id] ??= new \stdClass();
                $patches[$id]->{$key} = $patch;
            }
        }
        if (ConfigValues::props($generic) === []) {
            return [false, null, array_map(ConfigValues::clone(...), $values)];
        }
        return [true, $generic, $patches];
    }

    /**
     * @param array<string, mixed> $values
     * @return array{0: bool, 1: mixed, 2: array<string, mixed>}
     * @throws \JsonException
     */
    private function splitLeaf(array $values, int $minSupport, string $path): array
    {
        $groups = [];
        foreach ($values as $id => $value) {
            $groups[ConfigValues::canonical($value)][] = $id;
        }
        $bestKey = null;
        $bestCount = 0;
        foreach ($groups as $canonical => $ids) {
            if (count($ids) > $bestCount) { // ties: the first config (input order) wins
                $bestKey = (string)$canonical;
                $bestCount = count($ids);
            }
        }
        $generic = $bestCount >= $minSupport;
        $this->recordSupport($path, count($values), $bestCount, count($groups), $generic, 'value');
        if (!$generic) {
            return [false, null, array_map(ConfigValues::clone(...), $values)];
        }
        $patches = [];
        foreach ($values as $id => $value) {
            if (ConfigValues::canonical($value) !== $bestKey) {
                $patches[$id] = ConfigValues::clone($value);
            }
        }
        $first = $values[$groups[$bestKey][0]];
        return [true, ConfigValues::clone($first), $patches];
    }

    /**
     * Lists to which regions ADD items, where an item refers to layers: restrictions, and the lists of SEL. Generic
     * items can not be taken away by a region, so an item is generic when every config that has the layers it refers
     * to has it, and 2 or more configs have those layers (it is shared). An item that refers to a layer that only one
     * config has can not be generic. The merged order is generic items (sorted by content) first, then the additions.
     *
     * @param array<string, array> $lists config id => list
     * @param string $list the kind of list: "restrictions", or the key in SEL
     * @return array{0: bool, 1: array, 2: array<string, array>}
     * @throws \JsonException
     */
    private function splitAdditiveByLayers(array $lists, string $path, string $list): array
    {
        $ids = array_keys($lists);
        $have = [];  // canonical item => config ids that have it
        $first = []; // canonical item => the item, in the order of appearance
        foreach ($lists as $id => $items) {
            foreach ($items as $item) {
                $canonical = ConfigValues::canonical($item);
                $have[$canonical][$id] = true;
                $first[$canonical] ??= $item;
            }
        }
        $genericItems = [];
        foreach ($first as $canonical => $item) {
            $layers = $this->referencedLayers($list, $item);
            if ($layers === null) {
                continue;
            }
            $eligible = array_filter(
                $ids,
                fn($id) => array_diff($layers, array_keys($this->layersOf[$id] ?? [])) === []
            );
            if (count($eligible) >= 2 && array_diff($eligible, array_keys($have[$canonical])) === []) {
                $genericItems[(string)$canonical] = ConfigValues::clone($item);
            }
        }
        // in a fixed order, so that splitting configs that were split already gives the same generic config
        ksort($genericItems, SORT_STRING);
        $generic = array_values($genericItems);
        $genericSet = $genericItems;
        $patches = [];
        foreach ($lists as $id => $items) {
            $additions = [];
            foreach ($items as $item) {
                if (!isset($genericSet[ConfigValues::canonical($item)])) {
                    $additions[] = ConfigValues::clone($item);
                }
            }
            $patches[$id] = $additions;
        }
        $this->recordSupport($path, count($lists), count($generic), count($first), $generic !== [], 'list');
        if ($generic === []) {
            return [false, [], $patches];
        }
        return [true, $generic, array_filter($patches, static fn(array $additions) => $additions !== [])];
    }

    /**
     * The generic names of the shared layers that an item of a list refers to, null when it refers to something
     * else (a layer that only one config has, or a reference that is not understood): that can not be generic.
     *
     * @return ?string[]
     */
    private function referencedLayers(string $list, mixed $item): ?array
    {
        $references = match (true) {
            $list === 'restrictions' && $item instanceof \stdClass
                => [(string)($item->startlayer ?? ''), (string)($item->endlayer ?? '')],
            $list === 'shipping_lane_layers' && is_string($item)
                => [$item],
            in_array($list, ['port_layers', 'restriction_layer_exceptions'], true)
                && $item instanceof \stdClass && is_string($item->layer_name ?? null)
                => [$item->layer_name],
            default => null,
        };
        if ($references === null) {
            return null;
        }
        $layers = [];
        foreach ($references as $reference) {
            $base = LayerReferences::base($reference);
            if (!isset($this->shared[$base])) {
                return null;
            }
            $layers[$base] = true;
        }
        return array_map('strval', array_keys($layers));
    }

    /**
     * The layer_info_properties of the layers of one generic layer, by config. They are keyed by property_name: a
     * property is generic when every config has it and 2 or more configs have the same version of it (the most
     * common one). A config that has another version carries that version, which is merged into the generic one by
     * the name; the properties that are not generic stay in the configs that have them.
     *
     * @param array<string, array<int, mixed>> $properties config id => list
     * @return array{0: bool, 1: array, 2: array<string, array>} whether there is a generic list, that list, and what
     *         every config carries
     * @throws \JsonException
     */
    private function splitInfoProperties(array $properties): array
    {
        $byName = [];
        foreach ($properties as $id => $list) {
            foreach ($list as $item) {
                $name = LayerReferences::propertyName($item);
                if ($name === null || isset($byName[$id][$name])) {
                    return [false, [], $properties]; // not keyed: every config carries its own list
                }
                $byName[$id][$name] = $item;
            }
        }
        $names = null;
        foreach ($properties as $id => $_) {
            $mine = array_keys($byName[$id] ?? []);
            $names = $names === null ? $mine : array_intersect($names, $mine);
        }
        $generic = [];
        $genericCanonical = [];
        foreach ($names ?? [] as $name) {
            $versions = [];
            foreach ($properties as $id => $_) {
                $versions[ConfigValues::canonical($byName[$id][$name])][] = $id;
            }
            $best = null;
            foreach ($versions as $canonical => $sharing) { // ties: the first config (input order) wins
                if ($best === null || count($sharing) > count($versions[$best])) {
                    $best = (string)$canonical;
                }
            }
            if ($best !== null && count($versions[$best]) >= 2) {
                $generic[] = ConfigValues::clone($byName[$versions[$best][0]][(string)$name]);
                $genericCanonical[(string)$name] = $best;
            }
        }
        if ($generic === []) {
            return [false, [], $properties];
        }
        // what a config carries: first the versions that differ from the generic ones, in the order of the generic
        // properties (they are merged into those), then the properties that are not generic, in its own order. That
        // is the order of the merged list, so a config that was split already carries the same.
        $carried = [];
        foreach ($properties as $id => $list) {
            $carried[$id] = [];
            foreach ($genericCanonical as $name => $canonical) {
                if (ConfigValues::canonical($byName[$id][$name]) !== $canonical) {
                    $carried[$id][] = ConfigValues::clone($byName[$id][$name]);
                }
            }
            foreach ($list as $item) {
                if (!isset($genericCanonical[(string)LayerReferences::propertyName($item)])) {
                    $carried[$id][] = ConfigValues::clone($item);
                }
            }
        }
        return [true, $generic, $carried];
    }

    private function recordSupport(string $path, int $of, int $best, int $distinct, bool $generic, string $kind): void
    {
        if (!str_starts_with($path, 'meta')) {
            $this->support[$path] = compact('of', 'best', 'distinct', 'generic', 'kind');
        }
    }

    // ------------------------------------------------------------------------------------------------
    // output documents
    // ------------------------------------------------------------------------------------------------

    /**
     * @param array<string, \stdClass> $configs
     */
    private function genericMetadata(array $configs): \stdClass
    {
        $versions = [];
        foreach ($configs as $root) {
            $version = (string)($root->metadata->config_version ?? '');
            $versions[$version] = ($versions[$version] ?? 0) + 1;
        }
        arsort($versions);
        $metadata = new \stdClass();
        $metadata->config_version = (string)(array_key_first($versions) ?? '');
        return $metadata;
    }

    /**
     * @param \stdClass[] $meta region layer entries
     */
    private function buildRegionDocument(
        \stdClass $root,
        array $meta,
        ?\stdClass $restrictions,
        bool $emitDependencies,
        mixed $dependencies,
        ?\stdClass $simulation
    ): \stdClass {
        $document = new \stdClass();
        if (isset($root->metadata)) {
            $document->metadata = ConfigValues::clone($root->metadata);
        }
        $datamodel = new \stdClass();
        $done = [];
        foreach (ConfigValues::props($root->datamodel) as $key => $value) {
            switch ($key) {
                case 'CEL':
                case 'SEL':
                case 'MEL':
                case 'simulation_settings':
                    if (!isset($done['simulation_settings'])) {
                        // always present (also when empty): it is what marks a config as a region config
                        $datamodel->simulation_settings = $simulation ?? new \stdClass();
                    }
                    $done['simulation_settings'] = true;
                    break;
                case 'meta':
                    $datamodel->meta = $meta;
                    break;
                case 'restrictions':
                    if ($restrictions !== null) {
                        $datamodel->restrictions = $restrictions;
                    }
                    $done['restrictions'] = true;
                    break;
                case 'dependencies':
                    if ($emitDependencies) {
                        $datamodel->dependencies = $dependencies;
                    }
                    $done['dependencies'] = true;
                    break;
                default:
                    $datamodel->{$key} = ConfigValues::clone($value);
            }
        }
        if (!isset($done['simulation_settings'])) {
            $datamodel->simulation_settings = $simulation ?? new \stdClass();
        }
        if (!isset($done['restrictions']) && $restrictions !== null) {
            $datamodel->restrictions = $restrictions;
        }
        if (!isset($done['dependencies']) && $emitDependencies) {
            $datamodel->dependencies = $dependencies;
        }
        $document->datamodel = $datamodel;
        return $document;
    }
}
