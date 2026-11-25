<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;

/**
 * Compares two complete configs. Ignores object key order and 5 versus 5.0. Compares the additive lists
 * (restrictions, SEL.shipping_lane_layers / port_layers / restriction_layer_exceptions and
 * layer_info_properties) without regard to order, because merging puts generic items before region items.
 * Everything else, including the order of the layers, has to be identical.
 */
final class ConfigComparator
{
    /**
     * @return string[] human readable differences, empty when equal
     * @throws \JsonException
     */
    public function differences(\stdClass $expected, \stdClass $actual, int $limit = 20): array
    {
        $found = [];
        $add = static function (string $message) use (&$found, $limit): void {
            if (count($found) < $limit) {
                $found[] = $message;
            }
        };
        if (ConfigValues::canonical(
            $expected->metadata ?? null
        ) !== ConfigValues::canonical($actual->metadata ?? null)) {
            $add('metadata differs');
        }
        $a = $expected->datamodel ?? new \stdClass();
        $b = $actual->datamodel ?? new \stdClass();
        foreach (array_unique(array_merge(
            array_keys(ConfigValues::props($a)),
            array_keys(ConfigValues::props($b))
        )) as $key) {
            $key = (string)$key;
            $optional = in_array($key, ['restrictions', 'simulation_settings'], true); // absent means empty
            if (!$optional && (!ConfigValues::has($a, $key) || !ConfigValues::has($b, $key))) {
                $add("datamodel.$key is " . (ConfigValues::has($a, $key) ? 'missing after merging' :
                        'unexpected after merging'));
                continue;
            }
            match ($key) {
                'meta' => $this->compareLayers($a->meta, $b->meta, $add),
                'restrictions' => $this->compareMultiset(
                    LayerReferences::flattenRestrictions($a->restrictions ?? null),
                    LayerReferences::flattenRestrictions($b->restrictions ?? null),
                    'datamodel.restrictions',
                    $add
                ),
                'simulation_settings' => $this->compareSimulation(
                    $a->simulation_settings ?? null,
                    $b->simulation_settings ?? null,
                    $add
                ),
                default => ConfigValues::canonical($a->{$key} ?? null) === ConfigValues::canonical($b->{$key} ?? null)
                    ?: $add("datamodel.$key differs"),
            };
        }
        return $found;
    }

    /**
     * @throws \JsonException
     */
    private function compareLayers(mixed $a, mixed $b, \Closure $add): void
    {
        if (!is_array($a) || !is_array($b)) {
            ConfigValues::canonical($a) === ConfigValues::canonical($b) ?: $add('datamodel.meta differs');
            return;
        }
        if (count($a) !== count($b)) {
            $add(sprintf('datamodel.meta has %d layers, expected %d', count($b), count($a)));
            return;
        }
        foreach ($a as $index => $expected) {
            $actual = $b[$index];
            $name = $expected->layer_name ?? "#$index";
            foreach (array_unique(array_merge(array_keys(
                ConfigValues::props($expected)
            ), array_keys(ConfigValues::props($actual)))) as $key) {
                $key = (string)$key;
                if (!ConfigValues::has($expected, $key) || !ConfigValues::has($actual, $key)) {
                    $add("layer $name: $key " . (ConfigValues::has($expected, $key) ? 'missing' : 'unexpected'));
                    continue;
                }
                $x = $expected->{$key};
                $y = $actual->{$key};
                if ($key === 'layer_info_properties' && is_array($x) && is_array($y)) {
                    $this->compareMultiset($x, $y, "layer $name: layer_info_properties", $add);
                } elseif (ConfigValues::canonical($x) !== ConfigValues::canonical($y)) {
                    $add("layer $name: $key differs");
                }
            }
        }
    }

    /**
     * CEL, SEL and MEL; a missing one is the same as null.
     * @throws \JsonException
     */
    private function compareSimulation(mixed $a, mixed $b, \Closure $add): void
    {
        $a = $a instanceof \stdClass ? $a : new \stdClass();
        $b = $b instanceof \stdClass ? $b : new \stdClass();
        foreach (array_unique(array_merge(array_keys(
            ConfigValues::props($a)
        ), array_keys(ConfigValues::props($b)))) as $name) {
            $name = (string)$name;
            if ($name === 'SEL') {
                $this->compareSel($a->SEL ?? null, $b->SEL ?? null, $add);
            } elseif (ConfigValues::canonical($a->{$name} ?? null) !== ConfigValues::canonical($b->{$name} ?? null)) {
                $add("simulation_settings.$name differs");
            }
        }
    }

    /**
     * @throws \JsonException
     */
    private function compareSel(mixed $a, mixed $b, \Closure $add): void
    {
        if (!$a instanceof \stdClass || !$b instanceof \stdClass) {
            ConfigValues::canonical($a) === ConfigValues::canonical($b) ?: $add('simulation_settings.SEL differs');
            return;
        }
        foreach (array_unique(array_merge(array_keys(
            ConfigValues::props($a)
        ), array_keys(ConfigValues::props($b)))) as $key) {
            $key = (string)$key;
            if (!ConfigValues::has($a, $key) || !ConfigValues::has($b, $key)) {
                $add("SEL.$key " . (ConfigValues::has($a, $key) ? 'missing' : 'unexpected'));
            } elseif (in_array($key, ConfigSplitter::SEL_ADDITIVE, true) &&
                is_array($a->{$key}) && is_array($b->{$key})) {
                $this->compareMultiset($a->{$key}, $b->{$key}, "SEL.$key", $add);
            } elseif (ConfigValues::canonical($a->{$key}) !== ConfigValues::canonical($b->{$key})) {
                $add("SEL.$key differs");
            }
        }
    }

    /**
     * @throws \JsonException
     */
    private function compareMultiset(array $expected, array $actual, string $where, \Closure $add): void
    {
        $counts = [];
        foreach ($expected as $item) {
            $c = ConfigValues::canonical($item);
            $counts[$c] = ($counts[$c] ?? 0) + 1;
        }
        foreach ($actual as $item) {
            $c = ConfigValues::canonical($item);
            $counts[$c] = ($counts[$c] ?? 0) - 1;
        }
        $missing = array_sum(array_filter($counts, static fn($n) => $n > 0));
        $extra = -array_sum(array_filter($counts, static fn($n) => $n < 0));
        if ($missing || $extra) {
            $add("$where: $missing item(s) missing, $extra unexpected");
        }
    }
}
