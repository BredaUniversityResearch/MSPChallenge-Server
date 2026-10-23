<?php

namespace App\Domain\Config\Split;

/**
 * Helpers for decoded JSON where objects are \stdClass and lists are PHP arrays.
 *
 * Decoding with json_decode($json, false) keeps `{}` and `[]` distinct and keeps numeric-string keys
 * such as layer_type "0", "1" as object properties. Decoding to assoc arrays would turn those into
 * lists on re-encode, so do NOT switch this code to assoc arrays.
 */
final class ConfigValues
{
    public const int ENCODE_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * Stable string identity of a value: object key order is ignored, 5 and 5.0 are the same number.
     * @throws \JsonException
     */
    public static function canonical(mixed $value): string
    {
        if ($value instanceof \stdClass) {
            $props = [];
            foreach (get_object_vars($value) as $key => $item) {
                $props[(string)$key] = $item;
            }
            ksort($props, SORT_STRING);
            $parts = [];
            foreach ($props as $key => $item) {
                $parts[] = json_encode((string)$key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . ':' . self::canonical($item);
            }
            return '{' . implode(',', $parts) . '}';
        }
        if (is_array($value)) {
            return '[' . implode(',', array_map(self::canonical(...), $value)) . ']';
        }
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string)(int)$value;
        }
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    public static function clone(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $copy = new \stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $copy->{(string)$key} = self::clone($item);
            }
            return $copy;
        }
        if (is_array($value)) {
            return array_map(self::clone(...), $value);
        }
        return $value;
    }

    /**
     * @return array<string, mixed> property name => value, names always strings
     */
    public static function props(\stdClass $object): array
    {
        $props = [];
        foreach (get_object_vars($object) as $key => $item) {
            $props[(string)$key] = $item;
        }
        return $props;
    }

    public static function has(\stdClass $object, string $key): bool
    {
        return array_key_exists($key, get_object_vars($object));
    }

    public static function pascalCase(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_map(ucfirst(...), $words));
    }
}
