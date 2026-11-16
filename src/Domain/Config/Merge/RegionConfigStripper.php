<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;
use App\Domain\Config\Split\GenericNameRegistry;

/**
 * Removes everything from a config that the generic config already provides, so only the region specific
 * part remains. The result is a region config in the format ConfigSplitter writes.
 *
 * The input can be a complete (legacy) config or a region config that was stripped against an older
 * generic config: it is first expanded with the current generic config and then stripped again, so
 * stripping never changes what the config means under the current generic config:
 *
 *     merge(generic, strip(generic, X)) equals merge(generic, X)
 *
 * This is verified on every call. When the config cannot be stripped without changing its meaning (for
 * example it lacks an item the generic config would add to it) the result is "failed" and the caller keeps
 * the original file, which keeps working because complete configs are used as is.
 *
 * Layers: a layer whose generic counterpart (found through the layer name mapping) can be expressed as
 * overrides inherits from it. Any other layer (unknown, or not expressible) is kept as a standalone entry
 * named after its layer name.
 */
final class RegionConfigStripper
{
    private static ?\stdClass $same = null;

    public function __construct(
        private readonly RegionConfigMerger $merger = new RegionConfigMerger(),
        private readonly ConfigNormalizer $normalizer = new ConfigNormalizer(),
        private readonly ConfigComparator $comparator = new ConfigComparator()
    ) {
    }

    /**
     * @param bool $isEffective $config is already the final config (see RegionConfigMerger::merge()), for example
     *        expanded with an older generic config: it is not merged with $generic again
     * @param ?string $parent the name of the generic config ($generic), written in the metadata of the result as the
     *        parent of the config
     */
    public function strip(
        \stdClass $generic,
        \stdClass $config,
        GenericNameRegistry $names,
        bool $isEffective = false,
        ?string $parent = null
    ): StripResult {
        $warnings = [];
        $stats = ['layers inherited' => 0, 'layers standalone' => 0];
        $effective = $isEffective
            ? RegionConfigMerger::toSimulationSettings($config)
            : $this->merger->merge($generic, $config, $warnings);
        try {
            $region = $this->build($generic, $effective, $names, $stats, $warnings);
            if ($parent !== null) {
                $region->metadata ??= new \stdClass();
                $region->metadata->parent = $parent;
            }
        } catch (\DomainException $e) {
            return StripResult::failed([$e->getMessage()], $warnings);
        }

        $mergeWarnings = [];
        $differences = $this->comparator->differences(
            $this->normalizer->normalize($effective),
            $this->merger->merge($generic, $region, $mergeWarnings)
        );
        if ($differences !== []) {
            return StripResult::failed(
                array_merge(['Merging the stripped config would not give back the original:'], $differences),
                $warnings
            );
        }
        return StripResult::stripped($region, $stats, $warnings);
    }

    /**
     * @param array<string, int> $stats
     * @param string[] $warnings
     * @param-out array<string, int> $stats
     * @param-out string[] $warnings
     * @throws \JsonException
     * @throws \DomainException when the config cannot be expressed as a region config
     */
    private function build(
        \stdClass $generic,
        \stdClass $effective,
        GenericNameRegistry $names,
        array &$stats,
        array &$warnings
    ): \stdClass {
        $genericDatamodel = $generic->datamodel ?? new \stdClass();
        $datamodel = $effective->datamodel ?? throw new \DomainException('The config has no datamodel.');
        $genericLayers = [];
        foreach (is_array($genericDatamodel->meta ?? null) ? $genericDatamodel->meta : [] as $layer) {
            $genericLayers[$layer->msp_config_generic_name] = $layer;
        }

        // 1. layers
        $nameOf = []; // region layer name => name used in the region config
        $used = [];
        $entries = [];
        foreach (is_array($datamodel->meta ?? null) ? $datamodel->meta : [] as $layer) {
            $layerName = (string)$layer->layer_name;
            $candidate = $names->get($layerName);
            $entry = null;
            if ($candidate !== null && isset($genericLayers[$candidate]) && !isset($used[$candidate])) {
                try {
                    $entry = $this->inheritingEntry($genericLayers[$candidate], $layer, $candidate);
                    $name = $candidate;
                    $stats['layers inherited']++;
                } catch (\DomainException $e) {
                    $warnings[] = "Layer $layerName is kept standalone: " . $e->getMessage();
                }
            }
            if ($entry === null) {
                // no generic counterpart: the layer is kept as it is, without msp_config_generic_name. Sections of
                // the region refer to it by its layer name, unless that name is already taken by a generic layer
                $name = $layerName;
                $alias = false;
                while (isset($genericLayers[$name]) || isset($used[$name])) {
                    $name .= '_custom';
                    $alias = true;
                }
                $entry = $this->standaloneEntry($layer, $alias ? $name : null);
                $stats['layers standalone']++;
            }
            $used[$name] = true;
            $nameOf[$layerName] = $name;
            $entries[] = $entry;
        }
        $present = $used;
        $forward = function (string $reference) use ($nameOf, &$warnings): string {
            $parts = explode('|', $reference, 2);
            $base = $parts[0];
            if (!isset($nameOf[$base])) {
                $message = "Layer reference \"$reference\" is not a layer of this config, kept as is.";
                if (!in_array($message, $warnings, true)) {
                    $warnings[] = $message;
                }
                return $reference;
            }
            return isset($parts[1]) ? $nameOf[$base] . '|' . $parts[1] : $nameOf[$base];
        };

        // 2. restrictions
        $restrictions = null;
        $configEntries = [];
        foreach (LayerReferences::flattenRestrictions($datamodel->restrictions ?? null) as $entry) {
            $copy = ConfigValues::clone($entry);
            $copy->startlayer = $forward((string)($entry->startlayer ?? ''));
            $copy->endlayer = $forward((string)($entry->endlayer ?? ''));
            $configEntries[] = $copy;
        }
        $inherited = array_values(array_filter(
            LayerReferences::flattenRestrictions($genericDatamodel->restrictions ?? null),
            static fn($entry) => isset($present[LayerReferences::base((string)$entry->startlayer)])
                && isset($present[LayerReferences::base((string)$entry->endlayer)])
        ));
        $additions = $this->subtract($configEntries, $inherited, 'restriction');
        if ($additions !== []) {
            $restrictions = LayerReferences::groupRestrictions($additions);
        }

        // 3. dependencies
        $emitDependencies = false;
        if (ConfigValues::has($datamodel, 'dependencies')) {
            $emitDependencies = !ConfigValues::has($genericDatamodel, 'dependencies')
                || ConfigValues::canonical(
                    $genericDatamodel->dependencies
                ) !== ConfigValues::canonical($datamodel->dependencies);
        } elseif (ConfigValues::has($genericDatamodel, 'dependencies')) {
            throw new \DomainException('The config has no dependencies but the generic config has.');
        }

        // 4. simulation settings
        $simulation = $this->buildSimulation(
            $genericDatamodel->simulation_settings ?? null,
            $datamodel->simulation_settings ?? null,
            $present,
            $forward,
            $stats
        );

        // 5. assemble in the order of the config
        $region = new \stdClass();
        if (isset($effective->metadata)) {
            $region->metadata = ConfigValues::clone($effective->metadata);
        }
        $out = new \stdClass();
        $done = [];
        foreach (ConfigValues::props($datamodel) as $key => $value) {
            switch ($key) {
                case 'simulation_settings':
                    if (!isset($done['simulation_settings'])) {
                        // always present (also when empty): it is what marks a config as a region config
                        $out->simulation_settings = $simulation ?? new \stdClass();
                    }
                    $done['simulation_settings'] = true;
                    break;
                case 'meta':
                    $out->meta = $entries;
                    break;
                case 'restrictions':
                    if ($restrictions !== null) {
                        $out->restrictions = $restrictions;
                    }
                    $done['restrictions'] = true;
                    break;
                case 'dependencies':
                    if ($emitDependencies) {
                        $out->dependencies = ConfigValues::clone($value);
                    }
                    $done['dependencies'] = true;
                    break;
                default:
                    $out->{$key} = ConfigValues::clone($value);
            }
        }
        if (!isset($done['simulation_settings'])) {
            $out->simulation_settings = $simulation ?? new \stdClass();
        }
        $region->datamodel = $out;
        return $region;
    }

    /**
     * @throws \DomainException|\JsonException when the layer cannot be expressed as overrides of the generic layer
     */
    private function inheritingEntry(\stdClass $generic, \stdClass $layer, string $name): \stdClass
    {
        $entry = new \stdClass();
        $entry->msp_config_generic_name = $name;
        foreach (ConfigSplitter::REGION_LAYER_KEYS as $key) {
            if (ConfigValues::has($layer, $key)) {
                $entry->{$key} = ConfigValues::clone($layer->{$key});
            }
        }
        $genericBase = ConfigValues::clone($generic);
        unset($genericBase->msp_config_generic_name, $genericBase->layer_info_properties);
        $base = new \stdClass();
        $removed = ConfigSplitter::removedLayerKeys($layer);
        foreach (ConfigValues::props($layer) as $key => $value) {
            if (!in_array($key, $removed, true)
                && !in_array($key, ConfigSplitter::REGION_LAYER_KEYS, true)
                && $key !== 'layer_info_properties') {
                $base->{$key} = ConfigValues::clone($value);
            }
        }
        $patch = $this->diff($genericBase, $base, 'layer');
        if ($patch !== self::same()) {
            foreach (ConfigValues::props($patch) as $key => $value) {
                $entry->{$key} = $value;
            }
        }

        $genericProperties = $generic->layer_info_properties ?? null;
        $properties = $layer->layer_info_properties ?? null;
        if (is_array($genericProperties) && is_array($properties)) {
            $additions = $this->diffInfoProperties($genericProperties, $properties);
            if ($additions !== []) {
                $entry->layer_info_properties = $additions;
            }
        } elseif (ConfigValues::canonical($genericProperties) !== ConfigValues::canonical($properties)) {
            $entry->layer_info_properties = ConfigValues::clone($properties);
        }
        return $entry;
    }

    /**
     * What a layer has to say about layer_info_properties, when the generic layer has a list too: the items that
     * differ from the generic item with the same property_name (they are merged into it by that name), and the items
     * that the generic layer does not have.
     *
     * @param array<int, mixed> $generic
     * @param array<int, mixed> $properties
     * @return array<int, mixed>
     * @throws \DomainException when the generic layer has an item that the layer has not: merging would add it
     */
    private function diffInfoProperties(array $generic, array $properties): array
    {
        $genericByName = [];
        foreach ($generic as $item) {
            $name = LayerReferences::propertyName($item);
            if ($name === null || isset($genericByName[$name])) {
                // not keyed: items that are the same are the generic ones, the others are added
                return $this->subtract($properties, $generic, 'layer_info_properties item');
            }
            $genericByName[$name] = ConfigValues::canonical($item);
        }
        $mine = []; // property_name => the item of this layer that is merged into the generic one
        $rest = [];
        foreach ($properties as $item) {
            $name = LayerReferences::propertyName($item);
            if ($name !== null && isset($genericByName[$name]) && !isset($mine[$name])) {
                $mine[$name] = $item;
            } else {
                $rest[] = $item;
            }
        }
        // the versions that differ, in the order of the generic properties (they are merged into those), then the
        // others in their own order: the order of the merged list
        $emit = [];
        foreach ($genericByName as $name => $canonical) {
            if (!isset($mine[$name])) {
                throw new \DomainException(
                    'The generic config adds layer_info_properties item(s) that this config does not have; merging '
                    . 'would add them.'
                );
            }
            if (ConfigValues::canonical($mine[$name]) !== $canonical) {
                $emit[] = ConfigValues::clone($mine[$name]);
            }
        }
        foreach ($rest as $item) {
            $emit[] = ConfigValues::clone($item);
        }
        return $emit;
    }

    private function standaloneEntry(\stdClass $layer, ?string $alias): \stdClass
    {
        $entry = new \stdClass();
        if ($alias !== null) {
            $entry->msp_config_generic_name = $alias;
        }
        $removed = ConfigSplitter::removedLayerKeys($layer);
        foreach (ConfigValues::props($layer) as $key => $value) {
            if (!in_array($key, $removed, true)) {
                $entry->{$key} = ConfigValues::clone($value);
            }
        }
        return $entry;
    }

    /**
     * @param array<string, int> $stats
     * @param-out array<string, int> $stats
     * @return ?\stdClass the simulation_settings of the region config, null when it has nothing to say
     * @throws \DomainException
     */
    private function buildSimulation(
        mixed $generic,
        mixed $settings,
        array $present,
        \Closure $forward,
        array &$stats
    ): ?\stdClass {
        $simulation = new \stdClass();
        // every simulation there is: the ones that are always there, and every key of the generic and the config
        $names = array_unique(array_map('strval', array_merge(
            RegionConfigMerger::SIMULATIONS,
            array_keys($generic instanceof \stdClass ? ConfigValues::props($generic) : []),
            array_keys($settings instanceof \stdClass ? ConfigValues::props($settings) : [])
        )));
        foreach ($names as $name) {
            $genericPart = $generic instanceof \stdClass && ($generic->{$name} ?? null) instanceof \stdClass
                ? $generic->{$name} : null;
            $part = $settings instanceof \stdClass ? ($settings->{$name} ?? null) : null;
            if (!$part instanceof \stdClass) {
                // none (null), or something that is no object: it replaces what is generic, there is nothing to merge
                $hasKey = $settings instanceof \stdClass && ConfigValues::has($settings, $name);
                $genericHas = $generic instanceof \stdClass && ConfigValues::has($generic, $name);
                $genericValue = $genericHas ? $generic->{$name} : null;
                $standard = in_array($name, RegionConfigMerger::SIMULATIONS, true);
                if ($hasKey && $part === null) {
                    if ($genericValue !== null || (!$standard && !$genericHas)) {
                        $simulation->{$name} = null; // none: the generic one is not inherited
                    }
                } elseif ($hasKey && ($genericValue === null
                        || ConfigValues::canonical($genericValue) !== ConfigValues::canonical($part))) {
                    $simulation->{$name} = ConfigValues::clone($part);
                }
                continue;
            }
            $part = ConfigValues::clone($part);
            if ($name === 'SEL') {
                LayerReferences::mapSel($part, $forward);
                $patch = $genericPart === null ? $part : $this->diffSel($genericPart, $part, $present);
            } else {
                if ($name === 'MEL') {
                    foreach (is_array($part->ecologyCategories ?? null) ? $part->ecologyCategories : [] as $category) {
                        if ($category instanceof \stdClass && ConfigValues::has($category, 'valueDefinitions')) {
                            unset($category->valueDefinitions);
                            $stats['valueDefinitions removed'] = ($stats['valueDefinitions removed'] ?? 0) + 1;
                        }
                    }
                }
                if ($genericPart === null) {
                    $patch = $part;
                } else {
                    $patch = $this->diff($genericPart, $part, $name);
                    $patch = $patch === self::same() ? new \stdClass() : $patch;
                }
            }
            if ($genericPart === null || ConfigValues::props($patch) !== []) {
                $simulation->{$name} = $patch;
            }
        }
        return ConfigValues::props($simulation) === [] ? null : $simulation;
    }

    private function diffSel(\stdClass $generic, \stdClass $sel, array $present): \stdClass
    {
        $patch = new \stdClass();
        foreach (ConfigValues::props($sel) as $key => $value) {
            if (!ConfigValues::has($generic, $key)) {
                $patch->{$key} = ConfigValues::clone($value);
            } elseif (in_array($key, ConfigSplitter::SEL_ADDITIVE, true)
                && is_array($generic->{$key}) && is_array($value)) {
                $additions = $this->subtract(
                    $value,
                    LayerReferences::filterPresent($generic->{$key}, $present),
                    "SEL.$key item"
                );
                if ($additions !== []) {
                    $patch->{$key} = $additions;
                }
            } else {
                $sub = $this->diff($generic->{$key}, $value, "SEL.$key");
                if ($sub !== self::same()) {
                    $patch->{$key} = $sub;
                }
            }
        }
        foreach (ConfigValues::props($generic) as $key => $value) {
            if (ConfigValues::has($sel, $key)) {
                continue;
            }
            if (in_array($key, ConfigSplitter::SEL_ADDITIVE, true) && is_array($value)
                && LayerReferences::filterPresent($value, $present) === []) {
                continue;
            }
            throw new \DomainException("SEL.$key exists in the generic config but not in this config.");
        }
        return $patch;
    }

    /**
     * Smallest region value that, merged over $generic, gives $value: self::same() when equal, else the
     * patch. Objects are compared key by key, anything else is replaced.
     *
     * @throws \DomainException|\JsonException when $value
     *   lacks a key the generic object has (a region cannot remove keys)
     */
    private function diff(mixed $generic, mixed $value, string $where): mixed
    {
        if ($generic instanceof \stdClass && $value instanceof \stdClass) {
            $patch = new \stdClass();
            foreach (array_keys(ConfigValues::props($generic)) as $key) {
                if (!ConfigValues::has($value, (string)$key)) {
                    throw new \DomainException("$where lacks \"$key\", which the generic config has.");
                }
            }
            foreach (ConfigValues::props($value) as $key => $item) {
                if (!ConfigValues::has($generic, $key)) {
                    $patch->{$key} = ConfigValues::clone($item);
                    continue;
                }
                $sub = $this->diff($generic->{$key}, $item, "$where.$key");
                if ($sub !== self::same()) {
                    $patch->{$key} = $sub;
                }
            }
            return ConfigValues::props($patch) === [] ? self::same() : $patch;
        }
        return ConfigValues::canonical($generic) === ConfigValues::canonical($value)
            ? self::same()
            : ConfigValues::clone($value);
    }

    /**
     * $items without the items in $remove, keeping order. Every item of $remove has to be in $items.
     *
     * @throws \DomainException|\JsonException
     */
    private function subtract(array $items, array $remove, string $what): array
    {
        $counts = [];
        foreach ($remove as $item) {
            $c = ConfigValues::canonical($item);
            $counts[$c] = ($counts[$c] ?? 0) + 1;
        }
        $rest = [];
        foreach ($items as $item) {
            $c = ConfigValues::canonical($item);
            if (($counts[$c] ?? 0) > 0) {
                $counts[$c]--;
            } else {
                $rest[] = ConfigValues::clone($item);
            }
        }
        if (array_sum($counts) > 0) {
            throw new \DomainException(
                "The generic config adds $what(s) that this config does not have; merging would add them."
            );
        }
        return $rest;
    }

    private static function same(): \stdClass
    {
        return self::$same ??= new \stdClass();
    }
}
