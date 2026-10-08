<?php

namespace App\Domain\Config\Merge;

/**
 * Knows where a SEL object refers to layers, so references can be translated between region specific
 * layer names and generic names (and back).
 */
final class LayerReferences
{
    /** "NS_Wind_Farms_Implemented|0" => "NS_Wind_Farms_Implemented" */
    public static function base(string $reference): string
    {
        return explode('|', $reference, 2)[0];
    }

    /**
     * Applies $translate(string $reference): string to every layer reference in the SEL object, in place.
     */
    /**
     * The key of an item of layer_info_properties, null when the item has none.
     */
    public static function propertyName(mixed $item): ?string
    {
        return $item instanceof \stdClass && is_string($item->property_name ?? null) ? $item->property_name : null;
    }

    public static function mapSel(\stdClass $sel, \Closure $translate): void
    {
        if (is_array($sel->shipping_lane_layers ?? null)) {
            $sel->shipping_lane_layers = array_map(
                static fn($name) => is_string($name) ? $translate($name) : $name,
                $sel->shipping_lane_layers
            );
        }
        if (is_string($sel->country_border_layer ?? null)) {
            $sel->country_border_layer = $translate($sel->country_border_layer);
        }
        foreach (['port_layers', 'restriction_layer_exceptions', 'heatmap_settings'] as $key) {
            foreach (is_array($sel->{$key} ?? null) ? $sel->{$key} : [] as $item) {
                if ($item instanceof \stdClass && is_string($item->layer_name ?? null)) {
                    $item->layer_name = $translate($item->layer_name);
                }
            }
        }
        $risk = $sel->risk_heatmap_settings ?? null;
        if ($risk instanceof \stdClass && is_array($risk->restriction_layer_exceptions ?? null)) {
            $risk->restriction_layer_exceptions = array_map(
                static fn($name) => is_string($name) ? $translate($name) : $name,
                $risk->restriction_layer_exceptions
            );
        }
    }

    /**
     * The layer an item of an additive SEL list refers to (null if it does not refer to one).
     */
    public static function additiveItemReference(mixed $item): ?string
    {
        if (is_string($item)) {
            return $item;
        }
        if ($item instanceof \stdClass && is_string($item->layer_name ?? null)) {
            return $item->layer_name;
        }
        return null;
    }

    /**
     * Generic additive items only apply to a region that has the layer they refer to.
     *
     * @param array $items
     * @param array<string, mixed> $present generic names of the region's layers as keys
     * @return array
     */
    public static function filterPresent(array $items, array $present): array
    {
        return array_values(array_filter($items, static function ($item) use ($present): bool {
            $reference = self::additiveItemReference($item);
            return $reference === null || isset($present[self::base($reference)]);
        }));
    }

    /**
     * Restriction entries of the "restrictions" map as one flat list.
     *
     * @return \stdClass[]
     */
    public static function flattenRestrictions(mixed $restrictions): array
    {
        $entries = [];
        if ($restrictions instanceof \stdClass) {
            foreach (get_object_vars($restrictions) as $list) {
                foreach (is_array($list) ? $list : [] as $entry) {
                    $entries[] = $entry;
                }
            }
        }
        return $entries;
    }

    /**
     * @param \stdClass[] $entries
     */
    public static function groupRestrictions(array $entries): \stdClass
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[($entry->startlayer ?? '') . '|' . ($entry->endlayer ?? '')][] = $entry;
        }
        $object = new \stdClass();
        foreach ($groups as $key => $list) {
            $object->{(string)$key} = $list;
        }
        return $object;
    }
}
