<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigValues;

/**
 * Checks that normalizing a config changed nothing but what is documented. It walks the config as it was and the
 * normalized config at the same time, path by path, and every difference has to be on the list of documented changes:
 *
 * - CEL, MEL, REL and SEL that were keys of datamodel are in datamodel.simulation_settings, and CEL, MEL and SEL are
 *   there (null) also when the config has none;
 * - layer_information and layer_raster_filter_mode are removed from every layer, and layer_width and layer_height from
 *   the layers that are not raster layers;
 * - valueDefinitions are removed from the ecologyCategories of MEL;
 * - restrictions are the same entries, whatever the keys of the object that holds them (they are compared as a set);
 * - a number with a whole value is written without a fraction (1.0 is 1): a number is compared as a number;
 * - and the order of the keys is not looked at.
 *
 * Everything else has to be exactly the same: a value, the number of items of a list and their order, the layers and
 * their order.
 *
 * It does not use the normalizer, and not its lists of keys either: the lists below are a copy on purpose, so that a
 * mistake in the normalizer (a field that it removes and should not) shows up here as a difference. A test checks that
 * the two lists are the same.
 */
final class ConfigNormalizationAudit
{
    public const array REMOVED_LAYER_KEYS = ['layer_information', 'layer_raster_filter_mode'];
    public const array REMOVED_NON_RASTER_LAYER_KEYS = ['layer_width', 'layer_height'];
    public const array MOVED_SIMULATIONS = ['CEL', 'MEL', 'REL', 'SEL'];
    public const array ALWAYS_SIMULATIONS = ['CEL', 'MEL', 'SEL'];

    private const int LIMIT = 50;

    /**
     * @return string[] the differences that are not documented, one line each; empty when there are none
     */
    public function differences(\stdClass $source, \stdClass $result): array
    {
        $problems = [];
        $this->compare($source, $result, '', $problems);
        return $problems;
    }

    /**
     * @param string[] $problems
     */
    private function compare(mixed $a, mixed $b, string $path, array &$problems): void
    {
        if ($a instanceof \stdClass && $b instanceof \stdClass) {
            $this->compareObjects(ConfigValues::props($a), ConfigValues::props($b), $path, $problems);
            return;
        }
        if (is_array($a) && is_array($b)) {
            if (count($a) !== count($b)) {
                $this->problem($problems, sprintf('%s: a list of %d items became %d', $path, count($a), count($b)));
                return;
            }
            foreach (array_values($a) as $index => $item) {
                $this->compare($item, array_values($b)[$index], $path . '[' . $index . ']', $problems);
            }
            return;
        }
        $sameNumber = (is_int($a) || is_float($a)) && (is_int($b) || is_float($b)) && $a == $b;
        if ($a !== $b && !$sameNumber) {
            $this->problem($problems, sprintf('%s: %s became %s', $path, self::show($a), self::show($b)));
        }
    }

    /**
     * @param array<string|int, mixed> $a
     * @param array<string|int, mixed> $b
     * @param string[] $problems
     */
    private function compareObjects(array $a, array $b, string $path, array &$problems): void
    {
        foreach ($a as $key => $value) {
            $here = $path . '/' . $key;
            if (!array_key_exists($key, $b)) {
                if (!$this->mayBeRemoved($path, (string)$key)) {
                    $this->problem($problems, $here . ': only in the original');
                }
                continue;
            }
            if ($path === '' && $key === 'datamodel' && $value instanceof \stdClass && $b[$key] instanceof \stdClass) {
                $this->datamodel(ConfigValues::props($value), ConfigValues::props($b[$key]), $problems);
                continue;
            }
            $this->compare($value, $b[$key], $here, $problems);
        }
        foreach ($b as $key => $_) {
            if (!array_key_exists($key, $a)) {
                $this->problem($problems, $path . '/' . $key . ': only in the normalized config');
            }
        }
    }

    private function mayBeRemoved(string $path, string $key): bool
    {
        return $key === 'valueDefinitions'
            && preg_match('#^/datamodel/simulation_settings/MEL/ecologyCategories\[\d+]$#', $path) === 1;
    }

    /**
     * @param array<string|int, mixed> $a
     * @param array<string|int, mixed> $b
     * @param string[] $problems
     */
    private function datamodel(array $a, array $b, array &$problems): void
    {
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            $key = (string)$key;
            if ($key === 'simulation_settings' || in_array($key, self::MOVED_SIMULATIONS, true)) {
                continue; // the simulations: below
            }
            $here = '/datamodel/' . $key;
            if (!array_key_exists($key, $b)) {
                $this->problem($problems, $here . ': only in the original');
            } elseif (!array_key_exists($key, $a)) {
                $this->problem($problems, $here . ': only in the normalized config');
            } elseif ($key === 'meta') {
                $this->layers($a[$key], $b[$key], $problems);
            } elseif ($key === 'restrictions') {
                $this->restrictions($a[$key], $b[$key], $problems);
            } else {
                $this->compare($a[$key], $b[$key], $here, $problems);
            }
        }
        $this->simulations($a, $b, $problems);
    }

    /**
     * @param array<string|int, mixed> $a
     * @param array<string|int, mixed> $b
     * @param string[] $problems
     */
    private function simulations(array $a, array $b, array &$problems): void
    {
        $oldSettings = ($a['simulation_settings'] ?? null) instanceof \stdClass
            ? ConfigValues::props($a['simulation_settings']) : [];
        $newSettings = ($b['simulation_settings'] ?? null) instanceof \stdClass
            ? ConfigValues::props($b['simulation_settings']) : [];
        $names = array_unique(array_map('strval', array_merge(
            self::MOVED_SIMULATIONS,
            self::ALWAYS_SIMULATIONS,
            array_keys($oldSettings),
            array_keys($newSettings)
        )));
        foreach ($names as $name) {
            $here = '/datamodel/simulation_settings/' . $name;
            $old = in_array($name, self::MOVED_SIMULATIONS, true) && array_key_exists($name, $a);
            $inSettings = array_key_exists($name, $oldSettings);
            if ($old && $inSettings) {
                $this->problem($problems, $here . ': in the original as a key of datamodel and in simulation_settings');
                continue;
            }
            $present = $old || $inSettings;
            if (!array_key_exists($name, $newSettings)) {
                if ($present) {
                    $this->problem($problems, $here . ': only in the original');
                }
                continue;
            }
            if (!$present) {
                $allowed = in_array($name, self::ALWAYS_SIMULATIONS, true) && $newSettings[$name] === null;
                if (!$allowed) {
                    $this->problem($problems, $here . ': only in the normalized config');
                }
                continue;
            }
            $this->compare($old ? $a[$name] : $oldSettings[$name], $newSettings[$name], $here, $problems);
        }
    }

    /**
     * @param string[] $problems
     */
    private function layers(mixed $a, mixed $b, array &$problems): void
    {
        if (!is_array($a) || !is_array($b)) {
            $this->compare($a, $b, '/datamodel/meta', $problems);
            return;
        }
        if (count($a) !== count($b)) {
            $this->problem($problems, sprintf('/datamodel/meta: %d layers became %d', count($a), count($b)));
            return;
        }
        $b = array_values($b);
        foreach (array_values($a) as $index => $layer) {
            $other = $b[$index];
            $name = '';
            if ($layer instanceof \stdClass && is_string($layer->layer_name ?? null)) {
                $name = ' "' . $layer->layer_name . '"';
            }
            $here = '/datamodel/meta[' . $index . ']' . $name;
            if (!$layer instanceof \stdClass || !$other instanceof \stdClass) {
                $this->compare($layer, $other, $here, $problems);
                continue;
            }
            $removable = self::REMOVED_LAYER_KEYS;
            if (($layer->layer_geotype ?? null) !== 'raster') {
                $removable = array_merge($removable, self::REMOVED_NON_RASTER_LAYER_KEYS);
            }
            $props = ConfigValues::props($layer);
            $otherProps = ConfigValues::props($other);
            foreach ($props as $key => $value) {
                if (!array_key_exists($key, $otherProps)) {
                    if (!in_array((string)$key, $removable, true)) {
                        $this->problem($problems, $here . '/' . $key . ': only in the original');
                    }
                    continue;
                }
                $this->compare($value, $otherProps[$key], $here . '/' . $key, $problems);
            }
            foreach ($otherProps as $key => $_) {
                if (!array_key_exists($key, $props)) {
                    $this->problem($problems, $here . '/' . $key . ': only in the normalized config');
                }
            }
        }
    }

    /**
     * Restrictions are compared as the set of their entries, found wherever they are in the object: the keys that hold
     * them are rebuilt.
     *
     * @param string[] $problems
     */
    private function restrictions(mixed $a, mixed $b, array &$problems): void
    {
        $old = array_count_values($this->entries($a));
        $new = array_count_values($this->entries($b));
        foreach ($old as $entry => $count) {
            if (($new[$entry] ?? 0) < $count) {
                $this->problem(
                    $problems,
                    '/datamodel/restrictions: an entry is only in the original: ' . self::cut((string)$entry)
                );
            }
        }
        foreach ($new as $entry => $count) {
            if (($old[$entry] ?? 0) < $count) {
                $this->problem(
                    $problems,
                    '/datamodel/restrictions: an entry is only in the normalized config: ' . self::cut((string)$entry)
                );
            }
        }
    }

    /**
     * @return string[] every object with a startlayer and an endlayer below this value, as text
     */
    private function entries(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            $props = ConfigValues::props($value);
            if (array_key_exists('startlayer', $props) && array_key_exists('endlayer', $props)) {
                return [ConfigValues::canonical($value)];
            }
            $entries = [];
            foreach ($props as $item) {
                array_push($entries, ...$this->entries($item));
            }
            return $entries;
        }
        if (is_array($value)) {
            $entries = [];
            foreach ($value as $item) {
                array_push($entries, ...$this->entries($item));
            }
            return $entries;
        }
        return [];
    }

    /**
     * @param string[] $problems
     */
    private function problem(array &$problems, string $text): void
    {
        if (count($problems) < self::LIMIT) {
            $problems[] = $text;
        } elseif (count($problems) === self::LIMIT) {
            $problems[] = '(more differences are not listed)';
        }
    }

    private static function show(mixed $value): string
    {
        if ($value instanceof \stdClass || is_array($value)) {
            return $value instanceof \stdClass ? 'an object' : 'a list';
        }
        return self::cut((string)json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    private static function cut(string $text): string
    {
        return strlen($text) > 80 ? substr($text, 0, 77) . '...' : $text;
    }
}
