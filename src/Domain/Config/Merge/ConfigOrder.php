<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\Split\ConfigValues;

/**
 * The order of the keys in a config, so that the same config always looks the same, whatever it was made from. Used for
 * the final config (the merge) and for normalized configs, so that the two can be compared as text.
 *
 * - The keys of an object are in alphabetical order, the keys that are only digits first, in number order (the types
 *   of a layer: 0, 1, 2, 10). Nothing has a fixed list of keys: a new key (a new simulation, a new field)
 *   finds its place.
 * - A layer is the one exception: msp_config_generic_name first, then the keys of RegionConfigMerger::LAYER_KEY_ORDER
 *   (the name first, the long fields last), then every other key, alphabetically.
 * - A list keeps its order, and so do the layers of a config: the order of a list can mean something (the order in
 *   which the properties of a layer are shown).
 */
final class ConfigOrder
{
    /**
     * @return \stdClass a new config: the one that is given is not changed
     */
    public static function config(\stdClass $config): \stdClass
    {
        $ordered = self::value($config, '');
        return $ordered instanceof \stdClass ? $ordered : $config;
    }

    /**
     * The keys of a layer: msp_config_generic_name, the keys of LAYER_KEY_ORDER, and the others alphabetically. The
     * values are not looked at.
     */
    public static function layerKeys(\stdClass $layer): \stdClass
    {
        $keys = array_map('strval', array_keys(get_object_vars($layer)));
        $first = [];
        foreach (array_merge(['msp_config_generic_name'], RegionConfigMerger::LAYER_KEY_ORDER) as $key) {
            if (in_array($key, $keys, true)) {
                $first[] = $key;
            }
        }
        $rest = array_values(array_diff($keys, $first));
        usort($rest, self::compareKeys(...));
        $ordered = new \stdClass();
        foreach (array_merge($first, $rest) as $key) {
            $ordered->{$key} = $layer->{$key};
        }
        return $ordered;
    }

    public static function compareKeys(string|int $a, string|int $b): int
    {
        $a = (string)$a;
        $b = (string)$b;
        $aIsNumber = ctype_digit($a);
        $bIsNumber = ctype_digit($b);
        if ($aIsNumber && $bIsNumber) {
            return (int)$a <=> (int)$b;
        }
        if ($aIsNumber !== $bIsNumber) {
            return $aIsNumber ? -1 : 1;
        }
        return strcasecmp($a, $b) ?: strcmp($a, $b);
    }

    private static function value(mixed $value, string $path): mixed
    {
        if ($value instanceof \stdClass) {
            return self::object($value, $path);
        }
        if (is_array($value)) {
            $ordered = [];
            foreach ($value as $item) {
                $ordered[] = $path === 'datamodel.meta' && $item instanceof \stdClass
                    ? self::layer($item)
                    : self::value($item, $path . '[]');
            }
            return $ordered;
        }
        return $value;
    }

    private static function object(\stdClass $object, string $path): \stdClass
    {
        $keys = array_map('strval', array_keys(get_object_vars($object)));
        usort($keys, self::compareKeys(...));
        $ordered = new \stdClass();
        foreach ($keys as $key) {
            $ordered->{$key} = self::value($object->{$key}, $path === '' ? $key : $path . '.' . $key);
        }
        return $ordered;
    }

    private static function layer(\stdClass $layer): \stdClass
    {
        $keys = self::layerKeys($layer);
        $ordered = new \stdClass();
        foreach (get_object_vars($keys) as $key => $value) {
            $ordered->{$key} = self::value($value, 'datamodel.meta[].' . $key);
        }
        return $ordered;
    }
}
