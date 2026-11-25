<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Split\ConfigValues;

/**
 * Builds small, complete (legacy format) region configs for tests, as decoded JSON (objects, not arrays).
 */
final class ConfigFactory
{
    private const string LAYER = <<<'JSON'
        {
            "layer_name": "NAME",
            "layer_geotype": "polygon",
            "layer_entity_value_max": null,
            "layer_short": "SHORT",
            "layer_category": "management",
            "layer_subcategory": "governance",
            "layer_download_from_geoserver": 1,
            "layer_width": 1024,
            "layer_height": 1024,
            "layer_raster_material": "RasterMELNew",
            "layer_raster_filter_mode": 1,
            "layer_raster_color_interpolation": 0,
            "layer_raster_pattern": "Default",
            "layer_raster_minimum_value_cutoff": 0.05,
            "layer_active": 1,
            "layer_selectable": 1,
            "layer_editable": 0,
            "layer_toggleable": 1,
            "layer_active_on_start": 0,
            "layer_green": 0,
            "layer_tooltip": "",
            "layer_information": "",
            "layer_media": null,
            "layer_text_info": null,
            "layer_states": [{"state": "ASSEMBLY", "time": 0}, {"state": "ACTIVE", "time": 10}],
            "layer_editing_type": "",
            "layer_special_entity_type": "Default",
            "layer_depth": 1,
            "layer_property_as_type": null,
            "layer_type": {
                "0": {"displayName": "default", "approval": "EEZ", "value": 0, "polygonColor": "#6CFF1C80"},
                "1": {"displayName": "second", "approval": "EEZ", "value": 1, "polygonColor": "#FF0000FF"}
            },
            "layer_info_properties": null,
            "layer_tags": ["Polygon"]
        }
        JSON;

    /**
     * @throws \JsonException
     */
    public static function json(string $json): mixed
    {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Turns nested associative arrays into objects; lists stay lists. Objects are left alone.
     */
    public static function objects(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::objects(...), $value);
            }
            $object = new \stdClass();
            foreach ($value as $key => $item) {
                $object->{(string)$key} = self::objects($item);
            }
            return $object;
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $override layer fields to set (top level only)
     */
    public static function layer(string $name, string $short, array $override = []): \stdClass
    {
        $layer = self::json(self::LAYER);
        $layer->layer_name = $name;
        $layer->layer_short = $short;
        foreach ($override as $key => $value) {
            $layer->{$key} = self::objects($value);
        }
        return $layer;
    }

    /**
     * @param \stdClass[] $layers
     * @param array<string, mixed> $datamodel datamodel keys to set or replace (null removes the key)
     */
    public static function config(array $layers, array $datamodel = []): \stdClass
    {
        $config = self::json(<<<'JSON'
            {
                "metadata": {"date_modified": "01/01/2026", "data_model_hash": "ABC", "errors": 0,
                    "editor_version": "2.0.0", "config_version": "2.0.0"},
                "datamodel": {
                    "restrictions": {},
                    "plans": [],
                    "dependencies": {"groups": [{"name": "G", "entries": [{"name": "E", "id": 1}]}], "links": []},
                    "CEL": {"grey_centerpoint_color": "#3D1C04FF", "grey_centerpoint_sprite": "Oilbarrel",
                        "green_centerpoint_color": "#18840AFF", "green_centerpoint_sprite": "Lightning"},
                    "SEL": {
                        "shipping_lane_layers": [],
                        "country_border_layer": "EEZ",
                        "shipping_lane_point_merge_distance": 10000,
                        "shipping_lane_subdivide_distance": 50000,
                        "port_layers": [],
                        "port_intensity": [],
                        "maintenance_destinations": {"construction_intensity_multiplier": 2.5, "base_intensity": 5.0},
                        "output_configuration": {"pixels_per_mel_cell": 10, "simulation_area_cell_size": 5000},
                        "ship_types": [{"ship_type_id": 1, "ship_type_name": "Ferry"}]
                    },
                    "policy_settings": {"shipping": {"policy_type": "shipping", "enabled": true}},
                    "MEL": {
                        "modelfile": "MELdata/model.eiixml",
                        "rows": 10,
                        "ecologyCategories": [{"categoryName": "Biomass", "categoryColor": "#4575B4FF", "unit": "t/km2",
                            "valueDefinitions": [{"valueName": "Cod", "valueColor": "#FFC773FF"}]}]
                    },
                    "meta": [],
                    "expertise_definitions": [],
                    "objectives": [],
                    "edition_name": "Test edition",
                    "region": "test",
                    "start": 2020,
                    "end": 2050
                }
            }
            JSON);
        foreach ($datamodel as $key => $value) {
            if ($value === null && !in_array($key, ['SEL', 'MEL', 'CEL'], true)) {
                unset($config->datamodel->{$key});
            } else {
                $config->datamodel->{$key} = self::objects($value);
            }
        }
        $config->datamodel->meta = $layers;
        return $config;
    }

    /**
     * A restriction entry between two layers (as in the "restrictions" map of a config).
     */
    public static function restriction(string $start, string $end, string $message = 'Not allowed'): \stdClass
    {
        return self::json(json_encode([
            'message' => $message, 'value' => 0.0, 'type' => 'WARNING', 'startlayer' => $start, 'starttype' => '',
            'endlayer' => $end, 'endtype' => '', 'sort' => 'Inclusion',
        ], JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @param \stdClass[] $entries
     */
    public static function restrictions(array $entries): \stdClass
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry->startlayer . '|' . $entry->endlayer][] = $entry;
        }
        return self::objects($groups) instanceof \stdClass ? self::objects($groups) : new \stdClass();
    }

    public static function copy(\stdClass $config): \stdClass
    {
        return ConfigValues::clone($config);
    }
}
