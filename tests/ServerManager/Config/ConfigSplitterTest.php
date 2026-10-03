<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Split\ConfigValues;

class ConfigSplitterTest extends ConfigTestCase
{
    /**
     * A complete config with the layers Countries, Ports and Wind Farms, prefixed with the config's name.
     */
    private static function configFor(string $prefix, array $datamodel = []): \stdClass
    {
        $config = ConfigFactory::config([
            ConfigFactory::layer($prefix . '_Countries', 'Countries'),
            ConfigFactory::layer($prefix . '_Ports', 'Ports'),
            ConfigFactory::layer($prefix . '_Wind', 'Wind Farms'),
        ], $datamodel);
        if ($config->datamodel->SEL instanceof \stdClass) {
            $config->datamodel->SEL->country_border_layer = $prefix . '_Countries';
        }
        return $config;
    }

    private static function layerNames(array $layers): array
    {
        return array_map(static fn(\stdClass $layer) => $layer->msp_config_generic_name, $layers);
    }

    public function testSharedLayersBecomeGenericAndOnlyDifferencesStayInTheRegion(): void
    {
        $a = ConfigFactory::config([
            ConfigFactory::layer('A_Countries', 'Countries', ['layer_tooltip' => 'shared']),
            ConfigFactory::layer('A_Ports', 'Ports', ['layer_editable' => 0]),
        ]);
        $b = ConfigFactory::config([
            ConfigFactory::layer('B_Countries', 'Countries', ['layer_tooltip' => 'shared']),
            ConfigFactory::layer('B_Ports', 'Ports', ['layer_editable' => 1]),
            ConfigFactory::layer('B_Only', 'Only B'),
        ]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertSame(2, $result->genericLayerCount);
        $this->assertSame(2, $result->sharedLayerCount);
        $this->assertSame(1, $result->regionOnlyLayerCount);
        $this->assertSame(
            ['Countries', 'Ports'],
            self::layerNames($result->generic->datamodel->meta),
            'OnlyB is used by one config'
        );

        $countries = $result->generic->datamodel->meta[0];
        $this->assertSame('shared', $countries->layer_tooltip);
        $this->assertNull($countries->layer_info_properties);
        foreach (['layer_name', 'layer_download_from_geoserver', 'layer_raster_minimum_value_cutoff'] as $key) {
            $this->assertFalse(ConfigValues::has($countries, $key), "$key is region specific");
        }
        $this->assertSame(0, $result->generic->datamodel->meta[1]->layer_editable, 'a tie goes to the first config');

        $regionA = $result->regions['a/a']->datamodel->meta;
        $regionB = $result->regions['b/b']->datamodel->meta;
        $this->assertSame(
            [
                'msp_config_generic_name',
                'layer_name',
                'layer_download_from_geoserver',
                'layer_raster_minimum_value_cutoff'
            ],
            array_keys(get_object_vars($regionA[0])),
            'a layer without differences only keeps the region specific fields'
        );
        $this->assertSame('A_Countries', $regionA[0]->layer_name);
        $this->assertFalse(ConfigValues::has($regionA[1], 'layer_editable'));
        $only = $regionB[2]; // used by one config: complete, and without a generic name (nothing to match)
        $this->assertFalse(ConfigValues::has($only, 'msp_config_generic_name'));
        $this->assertSame('B_Only', $only->layer_name);
        $this->assertSame('polygon', $only->layer_geotype);
        $this->assertFalse(ConfigValues::has($only, 'layer_width'));
        $this->assertSame(1, $regionB[1]->layer_editable);
        $this->assertSame(['layer_editable' => 1], $result->layerOverrides);
    }

    public function testLayersUsedByOneConfigStayInThatConfigAndSectionsReferToThemByLayerName(): void
    {
        $a = self::configFor('A');
        $a->datamodel->SEL->shipping_lane_layers = ['A_Ports'];
        $b = self::configFor('B', ['restrictions' => ConfigFactory::restrictions([
            ConfigFactory::restriction('B_Countries', 'B_Only'),
        ])]);
        $b->datamodel->meta[] = ConfigFactory::layer('B_Only', 'Only B');
        $b->datamodel->SEL->shipping_lane_layers = ['B_Ports', 'B_Only'];

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $regionB = $result->regions['b/b']->datamodel;
        $this->assertSame(['Countries|B_Only'], array_keys(get_object_vars($regionB->restrictions)));
        $entry = $regionB->restrictions->{'Countries|B_Only'}[0];
        $this->assertSame('Countries', $entry->startlayer, 'a layer of the generic config: its generic name');
        $this->assertSame('B_Only', $entry->endlayer, 'a layer of this config only: its layer name');
        $this->assertSame(['B_Only'], $regionB->simulation_settings->SEL->shipping_lane_layers);
        // the layers and sections of the generic config never mention it (only its layer_names does, see below)
        $this->assertStringNotContainsString('B_Only', json_encode($result->generic->datamodel));
        $this->assertStringNotContainsString('OnlyB', json_encode($result->generic->datamodel));
        $this->assertSame(['B_Only'], $result->generic->layer_names->OnlyB, 'so it can join the generic config later');
        $this->assertSame([], self::differencesToOriginal($result->generic, $result->regions['b/b'], $b));
    }

    public function testTheGenericConfigHoldsTheLayerNamesOfAllLayersEvenOfThoseOnlyOneConfigHas(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->meta[] = ConfigFactory::layer('B_Only', 'Only B');

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $names = $result->generic->layer_names;
        $this->assertSame(['A_Countries', 'B_Countries'], $names->Countries);
        $this->assertSame(['B_Only'], $names->OnlyB, 'so the layer can join the generic config when another has it');
        $this->assertSame(
            ['metadata', 'datamodel', 'layer_names'],
            array_keys(get_object_vars($result->generic)),
            'next to the metadata and the datamodel, where merging does not look'
        );
    }

    public function testALayerOfOneConfigWithTheGenericNameOfAnotherLayerOfThatConfigIsRejected(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->meta[] = ConfigFactory::layer('Countries', 'Unique Thing');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only used by this config');
        self::split(['a/a' => $a, 'b/b' => $b]);
    }

    public function testARegionConfigAlwaysDeclaresItselfEvenWhenItHasNothingOfItsOwnToSay(): void
    {
        $a = self::configFor('A', ['SEL' => null, 'MEL' => null]);
        $b = self::configFor('B', ['SEL' => null, 'MEL' => null]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        foreach ($result->regions as $id => $region) {
            $this->assertTrue(RegionConfigMerger::isRegionFormat($region), $id);
            $this->assertSame([], get_object_vars($region->datamodel->simulation_settings), $id);
        }
    }

    public function testLayerTypesAreSplitPerTypeAndFieldWithoutDeleting(): void
    {
        $a = ConfigFactory::config([ConfigFactory::layer('A_Wind', 'Wind Farms')]);
        $b = ConfigFactory::config([ConfigFactory::layer('B_Wind', 'Wind Farms', [
            'layer_type' => ConfigFactory::json(<<<'JSON'
                {
                    "0": {"displayName": "default", "approval": "EEZ", "value": 0, "polygonColor": "#6CFF1C80"},
                    "1": {"displayName": "other", "approval": "EEZ", "value": 1, "polygonColor": "#FF0000FF"},
                    "2": {"displayName": "extra", "approval": "EEZ", "value": 2, "polygonColor": "#00FF00FF"}
                }
                JSON),
        ])]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $generic = $result->generic->datamodel->meta[0]->layer_type;
        $this->assertSame(['0', '1'], array_map(
            'strval',
            array_keys(get_object_vars($generic))
        ), 'only types every config has');
        $this->assertSame('second', $generic->{'1'}->displayName);
        $patch = $result->regions['b/b']->datamodel->meta[0]->layer_type;
        $this->assertSame(['displayName' => 'other'], get_object_vars($patch->{'1'}));
        $this->assertSame('extra', $patch->{'2'}->displayName);
        $this->assertFalse(ConfigValues::has($result->regions['a/a']->datamodel->meta[0], 'layer_type'));
    }

    public function testFieldsRemovedByTheDesignDocumentAreDroppedAndCounted(): void
    {
        $a = ConfigFactory::config([
            ConfigFactory::layer(
                'A_One',
                'One',
                ['layer_information' => 'wiki://One', 'layer_raster_filter_mode' => 2]
            ),
            ConfigFactory::layer('A_Two', 'Two'),
        ]);
        $b = ConfigFactory::config([ConfigFactory::layer('B_One', 'One')]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertSame(3, $result->dropped['layer_width / layer_height of layers that are not raster layers']);
        $this->assertSame(1, $result->dropped['layer_information with a non-empty value']);
        $this->assertSame(1, $result->dropped['layer_raster_filter_mode with a value other than 1']);
        $documents = array_merge([$result->generic], array_values($result->regions));
        foreach ($documents as $document) {
            foreach ($document->datamodel->meta as $layer) {
                foreach (['layer_width', 'layer_height', 'layer_raster_filter_mode', 'layer_information'] as $key) {
                    $this->assertFalse(
                        ConfigValues::has($layer, $key),
                        "$key must be removed from " . ($layer->layer_name ?? $layer->msp_config_generic_name)
                    );
                }
            }
        }
    }

    public function testRasterLayersKeepTheirWidthAndHeight(): void
    {
        // the server needs the layer_height of a raster layer to download it from GeoServer, and it differs per layer
        $raster = static fn(string $name, string $short, int $width, int $height) => ConfigFactory::layer(
            $name,
            $short,
            ['layer_geotype' => 'raster', 'layer_width' => $width, 'layer_height' => $height]
        );
        $a = ConfigFactory::config([
            $raster('A_Bathymetry', 'Bathymetry', 131, 113),
            ConfigFactory::layer('A_Countries', 'Countries'),
        ]);
        $b = ConfigFactory::config([
            $raster('B_Bathymetry', 'Bathymetry', 76, 44),
            ConfigFactory::layer('B_Countries', 'Countries'),
            $raster('B_Only', 'Only Raster', 1024, 2048),
        ]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $generic = $result->generic->datamodel->meta;
        $this->assertSame(['Bathymetry', 'Countries'], self::layerNames($generic));
        $this->assertSame(131, $generic[0]->layer_width, 'a tie goes to the first config');
        $this->assertSame(113, $generic[0]->layer_height);
        $this->assertFalse(ConfigValues::has($generic[1], 'layer_width'), 'removed from a layer that is not a raster');
        $this->assertFalse(ConfigValues::has($generic[1], 'layer_height'));
        $regionB = $result->regions['b/b']->datamodel->meta;
        $this->assertSame(76, $regionB[0]->layer_width, 'the size of this config is an override');
        $this->assertSame(44, $regionB[0]->layer_height);
        $this->assertSame(1024, $regionB[2]->layer_width, 'also in a raster layer that only this config has');
        $this->assertSame(2048, $regionB[2]->layer_height);
        $this->assertSame(
            2,
            $result->dropped['layer_width / layer_height of layers that are not raster layers']
        );
        $this->assertSame([], self::differencesToOriginal($result->generic, $result->regions['a/a'], $a));
        $this->assertSame([], self::differencesToOriginal($result->generic, $result->regions['b/b'], $b));
    }

    public function testRegionSpecificSectionsOnlyExistInTheRegionFile(): void
    {
        $specific = [
            'plans', 'policy_settings', 'objectives', 'expertise_definitions', 'edition_name', 'region', 'start', 'end'
        ];
        $a = self::configFor(
            'A',
            ['plans' => [['plan_id' => 1]], 'objectives' => [['country_id' => 3]], 'edition_name' => 'Edition A']
        );
        $b = self::configFor('B', ['plans' => [['plan_id' => 2]], 'objectives' => [], 'edition_name' => 'Edition B']);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        foreach ($specific as $key) {
            $this->assertFalse(ConfigValues::has($result->generic->datamodel, $key), "$key must not be generic");
        }
        foreach (['a/a' => $a, 'b/b' => $b] as $id => $original) {
            foreach ($specific as $key) {
                $this->assertSameJson(
                    $original->datamodel->{$key},
                    $result->regions[$id]->datamodel->{$key},
                    "$id $key"
                );
            }
            $this->assertSameJson($original->metadata, $result->regions[$id]->metadata);
        }
    }

    public function testCelSelAndMelLiveInSimulationSettings(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->MEL->rows = 20;

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $generic = $result->generic->datamodel;
        $this->assertEqualsCanonicalizing(
            ['meta', 'dependencies', 'simulation_settings'],
            self::datamodelKeys($result->generic)
        );
        foreach (['CEL', 'SEL', 'MEL'] as $name) {
            $this->assertFalse(ConfigValues::has($generic, $name), "$name must not be top-level in the generic config");
            foreach ($result->regions as $id => $region) {
                $this->assertFalse(ConfigValues::has($region->datamodel, $name), "$name must not be top-level in $id");
            }
        }
        $settings = $generic->simulation_settings;
        $this->assertSame('Oilbarrel', $settings->CEL->grey_centerpoint_sprite);
        $this->assertSame('Countries', $settings->SEL->country_border_layer, 'layer references become generic names');
        $this->assertSame(10000, $settings->SEL->shipping_lane_point_merge_distance);
        $this->assertSame(
            ['ecologyCategories'],
            array_keys(get_object_vars($settings->MEL)),
            'MEL is region specific except the categories'
        );
        $this->assertFalse(ConfigValues::has($settings->MEL->ecologyCategories[0], 'valueDefinitions'));
        $this->assertSame(2, $result->dropped['MEL ecologyCategories[].valueDefinitions']);

        $regionA = $result->regions['a/a']->datamodel->simulation_settings;
        $this->assertFalse(ConfigValues::has($regionA, 'CEL'), 'identical to the generic CEL');
        $this->assertFalse(ConfigValues::has($regionA->SEL, 'country_border_layer'));
        $this->assertTrue(
            ConfigValues::has($regionA->SEL, 'port_intensity'),
            'port_intensity is always region specific'
        );
        $this->assertSame(['modelfile', 'rows'], array_keys(get_object_vars($regionA->MEL)));
        $this->assertSame(20, $result->regions['b/b']->datamodel->simulation_settings->MEL->rows);
    }

    public function testCelOverrideStaysInTheRegion(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $c = self::configFor('C');
        $c->datamodel->CEL->grey_centerpoint_sprite = 'oilbarrel';

        [$result] = self::split(['a/a' => $a, 'b/b' => $b, 'c/c' => $c]);

        $this->assertSame('Oilbarrel', $result->generic->datamodel->simulation_settings->CEL->grey_centerpoint_sprite);
        $this->assertSame(
            ['grey_centerpoint_sprite' => 'oilbarrel'],
            get_object_vars($result->regions['c/c']->datamodel->simulation_settings->CEL)
        );
    }

    public function testRegionWithoutSelOrMelGetsAnExplicitNullSoItDoesNotInheritThem(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $c = self::configFor('C', ['SEL' => null, 'MEL' => null]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b, 'c/c' => $c]);

        $this->assertTrue(ConfigValues::has($result->generic->datamodel->simulation_settings, 'SEL'));
        $simulation = $result->regions['c/c']->datamodel->simulation_settings;
        $this->assertTrue(ConfigValues::has($simulation, 'SEL'));
        $this->assertNull($simulation->SEL);
        $this->assertTrue(ConfigValues::has($simulation, 'MEL'));
        $this->assertNull($simulation->MEL);
        $this->assertFalse(ConfigValues::has($simulation, 'CEL'));
    }

    public function testRestrictionsAreGenericOnlyWhenEveryConfigHasThemAndMapKeysAreRebuilt(): void
    {
        $restrictions = static function (string $prefix, bool $withSecond, bool $wrongKey): \stdClass {
            $map = new \stdClass();
            $first = ConfigFactory::restriction($prefix . '_Countries', $prefix . '_Ports');
            $map->{$wrongKey ? 'WRONG KEY' : $first->startlayer . '|' . $first->endlayer} = [$first];
            if ($withSecond) {
                $second = ConfigFactory::restriction($prefix . '_Countries', $prefix . '_Wind', 'Only some configs');
                $map->{$second->startlayer . '|' . $second->endlayer} = [$second];
            }
            return $map;
        };
        $a = self::configFor('A', ['restrictions' => $restrictions('A', true, true)]);
        $b = self::configFor('B', ['restrictions' => $restrictions('B', true, false)]);
        $c = self::configFor('C', ['restrictions' => $restrictions('C', false, false)]);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b, 'c/c' => $c]);

        $generic = $result->generic->datamodel->restrictions;
        $this->assertSame(['Countries|Ports'], array_keys(get_object_vars($generic)));
        $this->assertSame('Countries', $generic->{'Countries|Ports'}[0]->startlayer);
        $this->assertSame('Ports', $generic->{'Countries|Ports'}[0]->endlayer);
        foreach (['a/a', 'b/b'] as $id) {
            $this->assertSame(
                ['Countries|WindFarms'],
                array_keys(get_object_vars($result->regions[$id]->datamodel->restrictions))
            );
        }
        $this->assertFalse(ConfigValues::has($result->regions['c/c']->datamodel, 'restrictions'));
        $this->assertSame(1, $result->dropped['restriction map keys rewritten (did not match startlayer|endlayer)']);
    }

    public function testDependenciesAreGenericButAConfigReplacesThemAsAWhole(): void
    {
        $other = [
            'dependencies' =>
                ['groups' => [['name' => 'Other', 'entries' => [['name' => 'X', 'id' => 9]]]], 'links' => []]
        ];
        $a = self::configFor('A');
        $b = self::configFor('B');
        $c = self::configFor('C', $other);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b, 'c/c' => $c]);

        $this->assertSameJson($a->datamodel->dependencies, $result->generic->datamodel->dependencies);
        $this->assertFalse(ConfigValues::has($result->regions['a/a']->datamodel, 'dependencies'));
        $this->assertFalse(ConfigValues::has($result->regions['b/b']->datamodel, 'dependencies'));
        $this->assertSameJson($c->datamodel->dependencies, $result->regions['c/c']->datamodel->dependencies);
    }

    public function testSelListsTranslateLayerNamesAndRegionsAddTheirOwnItems(): void
    {
        $port = static fn(string $layer) => ['layer_name' => $layer, 'port_type' => 'DefinedPort'];
        $a = self::configFor('A');
        $a->datamodel->SEL->shipping_lane_layers = ['A_Ports'];
        $a->datamodel->SEL->port_layers = [ConfigFactory::objects($port('A_Ports'))];
        $b = self::configFor('B');
        $b->datamodel->SEL->shipping_lane_layers = ['B_Ports', 'B_Countries'];
        $b->datamodel->SEL->port_layers = [
            ConfigFactory::objects($port('B_Ports')), ConfigFactory::objects($port('B_Wind'))
        ];

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $genericSel = $result->generic->datamodel->simulation_settings->SEL;
        $this->assertSame(['Ports'], $genericSel->shipping_lane_layers);
        $this->assertSame('Ports', $genericSel->port_layers[0]->layer_name);
        $regionB = $result->regions['b/b']->datamodel->simulation_settings->SEL;
        $this->assertSame(['Countries'], $regionB->shipping_lane_layers);
        $this->assertSame('WindFarms', $regionB->port_layers[0]->layer_name);
        $this->assertFalse(
            ConfigValues::has($result->regions['a/a']->datamodel->simulation_settings->SEL, 'shipping_lane_layers')
        );
    }

    public function testSelKeysThatTheDesignDocumentDoesNotMentionStayRegionSpecificWithAWarning(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $a->datamodel->SEL->configured_routes = [ConfigFactory::objects(['source_port_id' => 'X'])];
        $b->datamodel->SEL->configured_routes = [ConfigFactory::objects(['source_port_id' => 'X'])];

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertFalse(
            ConfigValues::has($result->generic->datamodel->simulation_settings->SEL, 'configured_routes')
        );
        $this->assertTrue(
            ConfigValues::has($result->regions['a/a']->datamodel->simulation_settings->SEL, 'configured_routes')
        );
        $this->assertContains(
            'SEL.configured_routes is not covered by the design document; kept region specific.',
            $result->warnings
        );
    }

    public function testUnresolvableLayerReferencesAreKeptAndReported(): void
    {
        $a = self::configFor('A', ['restrictions' => ConfigFactory::restrictions([
            ConfigFactory::restriction('A_Countries', 'A_Gone'),
        ])]);
        $b = self::configFor('B');

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $entry = $result->regions['a/a']->datamodel->restrictions->{'Countries|A_Gone'}[0];
        $this->assertSame('A_Gone', $entry->endlayer);
        $this->assertNotEmpty(array_filter($result->warnings, static fn($w) => str_contains($w, '"A_Gone"')));
    }

    public function testRegionDocumentsKeepTheOrderOfTheLayers(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->meta = array_reverse($b->datamodel->meta);

        [$result] = self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertSame(
            ['B_Wind', 'B_Ports', 'B_Countries'],
            array_map(static fn($l) => $l->layer_name, $result->regions['b/b']->datamodel->meta)
        );
    }

    public function testInputsThatAlreadyUseSimulationSettingsGiveTheSameResult(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->MEL->rows = 20;
        [$expected] = self::split(['a/a' => $a, 'b/b' => $b]);

        [$actual] = self::split([
            'a/a' => RegionConfigMerger::toSimulationSettings($a),
            'b/b' => RegionConfigMerger::toSimulationSettings($b),
        ]);

        $this->assertSameJson($expected->generic, $actual->generic);
        $this->assertSameJson($expected->regions, $actual->regions);
    }

    public function testInputsAreNotModified(): void
    {
        $a = self::configFor(
            'A',
            ['restrictions' => ConfigFactory::restrictions([ConfigFactory::restriction('A_Countries', 'A_Ports')])]
        );
        $b = self::configFor('B');
        $before = self::canonical([$a, $b]);

        self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertSameContents($before, self::canonical([$a, $b]));
    }

    public function testSplittingIsDeterministic(): void
    {
        $a = self::configFor('A');
        $b = self::configFor('B');
        $b->datamodel->SEL->output_configuration->pixels_per_mel_cell = 20;

        [$first] = self::split(['a/a' => $a, 'b/b' => $b]);
        [$second] = self::split(['a/a' => $a, 'b/b' => $b]);

        $this->assertSame(
            json_encode($first->generic, ConfigValues::ENCODE_FLAGS),
            json_encode($second->generic, ConfigValues::ENCODE_FLAGS)
        );
        $this->assertSame(
            json_encode($first->regions, ConfigValues::ENCODE_FLAGS),
            json_encode($second->regions, ConfigValues::ENCODE_FLAGS)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testRealConfigsSplitLosslessly(): void
    {
        [$result] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame(
                [],
                self::differencesToOriginal($result->generic, $result->regions[$id], $original),
                "Merging the region file of $id with the generic config must give back the original"
            );
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealRegionFilesListEveryLayerInTheOriginalOrder(): void
    {
        [$result] = self::realSplit();
        $generic = self::layerNames($result->generic->datamodel->meta);

        foreach (self::realConfigs() as $id => $original) {
            $region = $result->regions[$id]->datamodel->meta;
            $this->assertSame(
                array_map(static fn($l) => $l->layer_name, $original->datamodel->meta),
                array_map(static fn($l) => $l->layer_name, $region),
                $id
            );
            foreach ($region as $entry) {
                if (isset($entry->msp_config_generic_name)) {
                    $this->assertContains($entry->msp_config_generic_name, $generic);
                }
            }
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealRegionFilesAreSmallerThanTheOriginals(): void
    {
        [$result] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $this->assertLessThan(self::byteSize($original), self::byteSize($result->regions[$id]), $id);
        }
    }
}
