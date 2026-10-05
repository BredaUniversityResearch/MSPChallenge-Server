<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Split\ConfigValues;

class RegionConfigMergerTest extends ConfigTestCase
{
    private const GENERIC = <<<'JSON'
        {
            "metadata": {"config_version": "2.0.0"},
            "datamodel": {
                "meta": [
                    {"msp_config_generic_name": "Countries", "layer_geotype": "polygon", "layer_short": "Countries",
                     "layer_tooltip": "generic tip", "layer_depth": 1,
                     "layer_type": {
                      "0": {"displayName": "default", "value": 0}, "1": {"displayName": "second", "value": 1}
                     },
                     "layer_info_properties": [{"property_name": "id", "enabled": 1}], "layer_tags": ["Polygon"]},
                    {"msp_config_generic_name": "Ports", "layer_geotype": "point", "layer_short": "Ports",
                     "layer_info_properties": null},
                    {"msp_config_generic_name": "WindFarms", "layer_geotype": "polygon", "layer_short": "Wind Farms",
                     "layer_info_properties": null}
                ],
                "restrictions": {
                    "Countries|Ports": [{"message": "m", "value": 0.0, "type": "WARNING", "startlayer": "Countries",
                        "starttype": "", "endlayer": "Ports", "endtype": "", "sort": "Inclusion"}],
                    "Countries|WindFarms": [{"message": "m", "value": 0.0, "type": "WARNING", "startlayer": "Countries",
                        "starttype": "", "endlayer": "WindFarms", "endtype": "", "sort": "Inclusion"}]
                },
                "dependencies": {"groups": [], "links": []},
                "simulation_settings": {
                    "CEL": {"a": 1, "b": 2},
                    "SEL": {
                        "shipping_lane_layers": ["Ports", "WindFarms"],
                        "country_border_layer": "Countries",
                        "port_layers": [{"layer_name": "Ports", "port_type": "DefinedPort"},
                            {"layer_name": "WindFarms", "port_type": "MaintenanceDestination"}],
                        "output_configuration": {"pixels_per_mel_cell": 10, "simulation_area_cell_size": 5000}
                    },
                    "MEL": {"ecologyCategories": [{"categoryName": "Biomass"}]}
                }
            }
        }
        JSON;

    private const REGION = <<<'JSON'
        {
            "metadata": {"config_version": "2.0.0", "date_modified": "01/01/2026"},
            "datamodel": {
                "meta": [
                    {"msp_config_generic_name": "Countries", "layer_name": "X_Countries",
                     "layer_download_from_geoserver": 1, "layer_raster_minimum_value_cutoff": 0.05,
                     "layer_tooltip": "own tip", "layer_type": {"1": {"displayName": "renamed"}},
                     "layer_info_properties": [{"property_name": "name", "enabled": 1}]},
                    {"msp_config_generic_name": "Ports", "layer_name": "X_Ports",
                     "layer_download_from_geoserver": 0, "layer_raster_minimum_value_cutoff": 0.05},
                    {"msp_config_generic_name": "Custom", "layer_name": "X_Custom", "layer_geotype": "line",
                     "layer_short": "Custom"}
                ],
                "restrictions": {
                    "Custom|Ports": [{"message": "own", "value": 0.0, "type": "WARNING", "startlayer": "Custom",
                        "starttype": "", "endlayer": "Ports", "endtype": "", "sort": "Inclusion"}]
                },
                "simulation_settings": {
                    "CEL": {"b": 3},
                    "SEL": {"port_layers": [{"layer_name": "Custom", "port_type": "DefinedPort"}],
                        "output_configuration": {"pixels_per_mel_cell": 20}},
                    "MEL": {"modelfile": "model.eiixml"}
                },
                "plans": [],
                "edition_name": "X edition"
            }
        }
        JSON;

    /**
     * @throws \JsonException
     */
    private static function generic(): \stdClass
    {
        return ConfigFactory::json(self::GENERIC);
    }

    /**
     * @throws \JsonException
     */
    private static function region(): \stdClass
    {
        return ConfigFactory::json(self::REGION);
    }

    private static function emptyGeneric(): \stdClass
    {
        return ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');
    }

    public function testRegionConfigIsCombinedWithTheGenericConfig(): void
    {
        $merged = self::merger()->merge(self::generic(), self::region());
        $datamodel = $merged->datamodel;

        $this->assertSame('01/01/2026', $merged->metadata->date_modified);
        $this->assertSame(
            ['meta', 'restrictions', 'simulation_settings', 'plans', 'edition_name', 'dependencies'],
            self::datamodelKeys($merged)
        );

        $countries = $datamodel->meta[0];
        $this->assertSame('X_Countries', $countries->layer_name);
        $this->assertSame('own tip', $countries->layer_tooltip, 'the region wins');
        $this->assertSame('polygon', $countries->layer_geotype, 'the rest comes from the generic layer');
        $this->assertSame('default', $countries->layer_type->{'0'}->displayName);
        $this->assertSame('renamed', $countries->layer_type->{'1'}->displayName);
        $this->assertSame(1, $countries->layer_type->{'1'}->value, 'objects merge key by key');
        $this->assertSame(
            ['id', 'name'],
            array_map(static fn($p) => $p->property_name, $countries->layer_info_properties)
        );
        $this->assertSame(0, $datamodel->meta[1]->layer_download_from_geoserver);
    }

    public function testMergedLayersHaveRegionLayerNamesAndNoGenericNames(): void
    {
        $merged = self::merger()->merge(self::generic(), self::region());

        $this->assertSame(
            ['X_Countries', 'X_Ports', 'X_Custom'],
            array_map(static fn($l) => $l->layer_name, $merged->datamodel->meta)
        );
        foreach ($merged->datamodel->meta as $layer) {
            $this->assertFalse(ConfigValues::has($layer, 'msp_config_generic_name'));
            $this->assertSame('layer_name', array_key_first(get_object_vars($layer)));
        }
    }

    public function testLayerWithoutGenericCounterpartIsUsedAsIs(): void
    {
        $merged = self::merger()->merge(self::generic(), self::region());

        $this->assertSame(
            ['layer_name' => 'X_Custom', 'layer_geotype' => 'line', 'layer_short' => 'Custom'],
            get_object_vars($merged->datamodel->meta[2])
        );
    }

    public function testGenericItemsForLayersTheRegionDoesNotHaveAreSkipped(): void
    {
        $merged = self::merger()->merge(self::generic(), self::region());
        $datamodel = $merged->datamodel;

        $this->assertSame(
            ['X_Countries|X_Ports', 'X_Custom|X_Ports'],
            array_keys(get_object_vars($datamodel->restrictions))
        );
        $sel = $datamodel->simulation_settings->SEL;
        $this->assertSame(
            ['X_Ports'],
            $sel->shipping_lane_layers,
            'WindFarms is not a layer of this region'
        );
        $this->assertSame(
            ['X_Ports', 'X_Custom'],
            array_map(static fn($p) => $p->layer_name, $sel->port_layers),
            'generic items first, then the region\'s'
        );
    }

    public function testSimulationSettingsAreMergedAndLayerReferencesBecomeRegionLayerNames(): void
    {
        $settings = self::merger()->merge(self::generic(), self::region())->datamodel->simulation_settings;

        $this->assertSame(['CEL', 'SEL', 'MEL'], array_keys(get_object_vars($settings)));
        $this->assertSame(['a' => 1, 'b' => 3], get_object_vars($settings->CEL));
        $this->assertSame('X_Countries', $settings->SEL->country_border_layer);
        $this->assertSame(
            ['pixels_per_mel_cell' => 20, 'simulation_area_cell_size' => 5000],
            get_object_vars($settings->SEL->output_configuration)
        );
        $this->assertSame('model.eiixml', $settings->MEL->modelfile);
        $this->assertSame('Biomass', $settings->MEL->ecologyCategories[0]->categoryName);
    }

    public function testALayerWithoutAGenericNameIsUsedAsItIsAndSectionsReferToItByItsLayerName(): void
    {
        $region = self::region();
        unset($region->datamodel->meta[2]->msp_config_generic_name);
        $region->datamodel->meta[2]->layer_name = 'X_Custom';
        $region->datamodel->restrictions = ConfigFactory::json(
            '{"X_Custom|Ports": [{"message": "own", "value": 0.0, "type": "WARNING", "startlayer": "X_Custom",
                "starttype": "", "endlayer": "Ports", "endtype": "", "sort": "Inclusion"}]}'
        );
        $region->datamodel->simulation_settings->SEL->port_layers = [
            ConfigFactory::json('{"layer_name": "X_Custom", "port_type": "DefinedPort"}'),
        ];

        $warnings = [];
        $merged = self::merger()->merge(self::generic(), $region, $warnings);

        $this->assertSame([], $warnings, 'a layer name is a valid reference');
        $this->assertSame(
            ['X_Countries|X_Ports', 'X_Custom|X_Ports'],
            array_keys(get_object_vars($merged->datamodel->restrictions))
        );
        $this->assertSame(
            ['X_Ports', 'X_Custom'],
            array_map(static fn($p) => $p->layer_name, $merged->datamodel->simulation_settings->SEL->port_layers)
        );
        $this->assertSame('X_Custom', $merged->datamodel->meta[2]->layer_name);
    }

    public function testARegionConfigWithOnlyLayersOfItsOwnStillInheritsFromTheGenericConfig(): void
    {
        $region = ConfigFactory::json(
            '{"datamodel": {"meta": [{"layer_name": "X_Mine", "layer_geotype": "point"}], "simulation_settings": {}}}'
        );

        $this->assertTrue(RegionConfigMerger::isRegionFormat($region));
        $merged = self::merger()->merge(self::generic(), $region);

        $this->assertSame(['a' => 1, 'b' => 2], get_object_vars($merged->datamodel->simulation_settings->CEL));
        $this->assertSame(['X_Mine'], array_map(static fn($l) => $l->layer_name, $merged->datamodel->meta));
    }

    /**
     * @return array{0: \stdClass, 1: \stdClass} a parent and a child generic config
     */
    private static function parentAndChild(): array
    {
        $restriction = static fn(string $start, string $end, string $message): string => json_encode([[
            'message' => $message, 'value' => 0.0, 'type' => 'ERROR',
            'startlayer' => $start, 'starttype' => '', 'endlayer' => $end, 'endtype' => '', 'sort' => 'Inclusion',
        ]]);
        $parent = ConfigFactory::json('{
            "metadata": {"config_version": "2.0.0"},
            "datamodel": {
                "meta": [
                    {"msp_config_generic_name": "A", "layer_category": "x", "layer_tooltip": "a"},
                    {"msp_config_generic_name": "B", "layer_category": "x", "layer_tooltip": "b",
                     "layer_info_properties": [{"property_name": "p1"}]}
                ],
                "restrictions": {"A|B": ' . $restriction('A', 'B', 'of the parent') . '},
                "dependencies": {"a": 1},
                "simulation_settings": {
                    "CEL": {"a": 1, "b": 2},
                    "SEL": {"port_layers": [{"layer_name": "A"}], "ship": 1},
                    "MEL": null
                }
            }
        }');
        $child = ConfigFactory::json('{
            "metadata": {"config_version": "2.0.0", "parent": "base"},
            "datamodel": {
                "meta": [
                    {"msp_config_generic_name": "B", "layer_tooltip": "b of the child",
                     "layer_info_properties": [{"property_name": "p2"}]},
                    {"msp_config_generic_name": "C", "layer_category": "y"}
                ],
                "restrictions": {"B|C": ' . $restriction('B', 'C', 'of the child') . '},
                "dependencies": {"b": 2},
                "simulation_settings": {"CEL": {"b": 3}, "SEL": {"port_layers": [{"layer_name": "C"}]}}
            }
        }');
        return [$parent, $child];
    }

    public function testOnlyAConfigThatNeedsAParentIsStripped(): void
    {
        $withParent = ConfigFactory::json('{"metadata": {"parent": "generic"}, "datamodel": {"meta": []}}');
        $withGenericNames = ConfigFactory::json('{"datamodel": {"meta": [{"msp_config_generic_name": "A"}]}}');
        $completeNewShape = ConfigFactory::json(
            '{"metadata": {}, "datamodel": {"meta": [{"layer_name": "X_A"}], "simulation_settings": {"CEL": null}}}'
        );
        $completeOldShape = ConfigFactory::json('{"datamodel": {"meta": [{"layer_name": "X_A"}], "CEL": null}}');

        $this->assertTrue(RegionConfigMerger::isStripped($withParent));
        $this->assertTrue(RegionConfigMerger::isStripped($withGenericNames));
        $this->assertFalse(RegionConfigMerger::isStripped($completeNewShape), 'what an upload stores');
        $this->assertTrue(RegionConfigMerger::isRegionFormat($completeNewShape), 'but it has the new shape');
        $this->assertFalse(RegionConfigMerger::isStripped($completeOldShape));
    }

    public function testEverySimulationInSimulationSettingsIsMerged(): void
    {
        $generic = self::generic();
        $generic->datamodel->simulation_settings->REL = ConfigFactory::objects(['a' => 1, 'b' => 1]);
        $generic->datamodel->simulation_settings->OnlyGeneric = ConfigFactory::objects(['g' => 1]);
        $generic->datamodel->simulation_settings->Dropped = ConfigFactory::objects(['g' => 1]);
        $region = self::region();
        $region->datamodel->simulation_settings->REL = ConfigFactory::objects(['b' => 2]);
        $region->datamodel->simulation_settings->ExternalSim = ConfigFactory::objects(['url' => 'https://sim']);
        $region->datamodel->simulation_settings->Dropped = null;
        $region->datamodel->simulation_settings->Replaced = 5;
        $generic->datamodel->simulation_settings->Replaced = ConfigFactory::objects(['x' => 1]);

        $settings = self::merger()->merge($generic, $region)->datamodel->simulation_settings;

        $this->assertSame(['a' => 1, 'b' => 2], get_object_vars($settings->REL), 'objects merge, the region wins');
        $this->assertSame(['g' => 1], get_object_vars($settings->OnlyGeneric), 'inherited');
        $this->assertSame(['url' => 'https://sim'], get_object_vars($settings->ExternalSim), 'only in the region');
        $this->assertTrue(ConfigValues::has($settings, 'Dropped'));
        $this->assertNull($settings->Dropped, 'null: this region has none, the generic one is not inherited');
        $this->assertSame(5, $settings->Replaced, 'something that is no object replaces');
        foreach (['CEL', 'SEL', 'MEL'] as $name) {
            $this->assertTrue(ConfigValues::has($settings, $name), "$name is always there");
        }
    }

    public function testASimulationThatNobodyHasIsNotInTheResultExceptTheOnesThatAreAlwaysThere(): void
    {
        $region = self::region();
        unset($region->datamodel->simulation_settings->SEL);

        $settings = self::merger()->merge(self::emptyGeneric(), $region)->datamodel->simulation_settings;

        $this->assertSame(['CEL', 'SEL', 'MEL'], array_keys(get_object_vars($settings)));
        $this->assertNull($settings->SEL);
    }

    public function testAnOldStyleRelIsMovedToSimulationSettings(): void
    {
        $region = self::region();
        $region->datamodel->REL = ConfigFactory::objects(['x' => 1]);

        $merged = self::merger()->merge(self::generic(), $region);

        $this->assertSame(['x' => 1], get_object_vars($merged->datamodel->simulation_settings->REL));
        $this->assertFalse(ConfigValues::has($merged->datamodel, 'REL'), 'old-style keys are not in the result');

        $complete = ConfigFactory::config([ConfigFactory::layer('X_A', 'A')]);
        $complete->datamodel->REL = ConfigFactory::objects(['y' => 2]);
        $moved = RegionConfigMerger::toSimulationSettings($complete);
        $this->assertSame(['y' => 2], get_object_vars($moved->datamodel->simulation_settings->REL));
        $this->assertFalse(ConfigValues::has($moved->datamodel, 'REL'));
    }

    public function testLayerInfoPropertiesAreMergedByPropertyName(): void
    {
        $generic = ConfigFactory::json('{"datamodel": {"meta": [{
            "msp_config_generic_name": "A", "layer_info_properties": [
            {"property_name": "id", "enabled": 1, "display_name": "from the generic config"},
            {"property_name": "name", "enabled": 1}
        ]}]}}');
        $region = ConfigFactory::json('{"metadata": {"parent": "x"}, "datamodel": {"meta": [{
            "msp_config_generic_name": "A", "layer_name": "X_A", "layer_info_properties": [
                {"property_name": "new", "enabled": 1},
                {"property_name": "id", "enabled": 0},
                {"property_name": "id", "enabled": 5}
        ]}]}}');

        $merged = self::merger()->merge($generic, $region)->datamodel->meta[0]->layer_info_properties;

        $this->assertSame(
            [
                ['property_name' => 'id', 'enabled' => 0, 'display_name' => 'from the generic config'],
                ['property_name' => 'name', 'enabled' => 1],
                ['property_name' => 'new', 'enabled' => 1],
                ['property_name' => 'id', 'enabled' => 5],
            ],
            array_map('get_object_vars', $merged),
            'the generic order, the region wins field by field, then the others; a second one with a name is added'
        );
    }

    public function testTwoGenericConfigsMergeEverySimulation(): void
    {
        $parent = ConfigFactory::json(
            '{"datamodel": {"meta": [], "simulation_settings": {"REL": {"a": 1, "b": 1}, "Old": {"x": 1}}}}'
        );
        $child = ConfigFactory::json(
            '{"datamodel": {"meta": [], "simulation_settings": {"REL": {"b": 2}, "New": {"y": 1}, "Old": null}}}'
        );

        $settings = self::merger()->mergeGeneric($parent, $child)->datamodel->simulation_settings;

        $this->assertSame(['a' => 1, 'b' => 2], get_object_vars($settings->REL));
        $this->assertSame(['y' => 1], get_object_vars($settings->New));
        $this->assertNull($settings->Old);
    }

    public function testTwoGenericConfigsMergeLayerInfoPropertiesByPropertyName(): void
    {
        $parent = ConfigFactory::json('{"datamodel": {"meta": [{
            "msp_config_generic_name": "A", "layer_info_properties": [
            {"property_name": "id", "enabled": 1}, {"property_name": "name", "enabled": 1}]}]}}');
        $child = ConfigFactory::json('{"datamodel": {"meta": [{
            "msp_config_generic_name": "A", "layer_info_properties": [
            {"property_name": "id", "enabled": 0}, {"property_name": "extra", "enabled": 1}]}]}}');

        $merged = self::merger()->mergeGeneric($parent, $child)->datamodel->meta[0]->layer_info_properties;

        $this->assertSame(
            [['property_name' => 'id', 'enabled' => 0], ['property_name' => 'name', 'enabled' => 1],
                ['property_name' => 'extra', 'enabled' => 1]],
            array_map('get_object_vars', $merged)
        );
    }

    public function testTwoGenericConfigsAreMergedByLayerName(): void
    {
        [$parent, $child] = self::parentAndChild();

        $pool = self::merger()->mergeGeneric($parent, $child);

        $layers = $pool->datamodel->meta;
        $this->assertSame(['A', 'B', 'C'], array_map(static fn($l) => $l->msp_config_generic_name, $layers));
        $this->assertSame('msp_config_generic_name', array_key_first(get_object_vars($layers[1])), 'the name stays');
        $this->assertSame('b of the child', $layers[1]->layer_tooltip, 'the child changes a layer of the parent');
        $this->assertSame('x', $layers[1]->layer_category, 'and what it does not change stays');
        $this->assertSame(
            ['p1', 'p2'],
            array_map(static fn($p) => $p->property_name, $layers[1]->layer_info_properties),
            'layer_info_properties of the child add to the ones of the parent'
        );
        $this->assertSame('y', $layers[2]->layer_category, 'and it adds layers');
    }

    public function testTwoGenericConfigsCombineTheirSections(): void
    {
        [$parent, $child] = self::parentAndChild();

        $pool = self::merger()->mergeGeneric($parent, $child);

        $datamodel = $pool->datamodel;
        $this->assertSame(['A|B', 'B|C'], array_keys(get_object_vars($datamodel->restrictions)), 'restrictions add up');
        $this->assertSame(
            ['b' => 2],
            get_object_vars($datamodel->dependencies),
            'dependencies are replaced as a whole'
        );
        $settings = $datamodel->simulation_settings;
        $this->assertSame(['a' => 1, 'b' => 3], get_object_vars($settings->CEL), 'values: the child wins');
        $this->assertSame(
            ['A', 'C'],
            array_map(static fn($port) => $port->layer_name, $settings->SEL->port_layers),
            'lists of layers add up'
        );
        $this->assertSame(1, $settings->SEL->ship);
        $this->assertNull($settings->MEL);
    }

    public function testTheMergeOfTwoGenericConfigsIsAGenericConfigThatCanBeMergedAgain(): void
    {
        [$parent, $child] = self::parentAndChild();
        $grandChild = ConfigFactory::json(
            '{"metadata": {"parent": "child"}, "datamodel": {"meta": [{"msp_config_generic_name": "D"}]}}'
        );

        $pool = self::merger()->mergeGeneric(self::merger()->mergeGeneric($parent, $child), $grandChild);

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            array_map(static fn($l) => $l->msp_config_generic_name, $pool->datamodel->meta)
        );
        $this->assertSame(['b' => 2], get_object_vars($pool->datamodel->dependencies), 'sections carry on');
    }

    public function testTheMetadataOfAMergedGenericConfigIsThatOfTheChildWithoutItsParent(): void
    {
        [$parent, $child] = self::parentAndChild();

        $pool = self::merger()->mergeGeneric($parent, $child);

        $this->assertSame(['config_version' => '2.0.0'], get_object_vars($pool->metadata));
    }

    public function testMergingTwoGenericConfigsChangesNeitherOfThem(): void
    {
        [$parent, $child] = self::parentAndChild();
        $before = [self::canonical($parent), self::canonical($child)];

        self::merger()->mergeGeneric($parent, $child);

        $this->assertSame($before, [self::canonical($parent), self::canonical($child)]);
    }

    public function testRegionWithoutDependenciesInheritsTheGenericOnes(): void
    {
        $merged = self::merger()->merge(self::generic(), self::region());

        $this->assertSameJson(self::generic()->datamodel->dependencies, $merged->datamodel->dependencies);
    }

    /**
     * @throws \JsonException
     */
    public function testRegionDependenciesReplaceTheGenericOnesAsAWhole(): void
    {
        $region = self::region();
        $region->datamodel->dependencies = ConfigFactory::json(
            '{"groups": [{"name": "Own", "entries": []}], "links": []}'
        );

        $merged = self::merger()->merge(self::generic(), $region);

        $this->assertSameJson($region->datamodel->dependencies, $merged->datamodel->dependencies);
    }

    /**
     * @throws \JsonException
     */
    public function testAnExplicitNullDoesNotInheritTheGenericSimulation(): void
    {
        $region = self::region();
        $region->datamodel->simulation_settings = ConfigFactory::json('{"SEL": null, "MEL": null}');

        $settings = self::merger()->merge(self::generic(), $region)->datamodel->simulation_settings;

        $this->assertTrue(ConfigValues::has($settings, 'SEL'));
        $this->assertNull($settings->SEL);
        $this->assertNull($settings->MEL);
        $this->assertSame(['a' => 1, 'b' => 2], get_object_vars($settings->CEL), 'CEL is still inherited');
    }

    /**
     * @throws \JsonException
     */
    public function testASimulationNeitherSideHasIsNull(): void
    {
        $generic = self::generic();
        unset($generic->datamodel->simulation_settings);
        $region = self::region();
        unset($region->datamodel->simulation_settings);

        $settings = self::merger()->merge($generic, $region)->datamodel->simulation_settings;

        $this->assertSame(['CEL' => null, 'SEL' => null, 'MEL' => null], get_object_vars($settings));
    }

    /**
     * @throws \JsonException
     */
    public function testReferencesToLayersThatAreNotInTheRegionAreKeptAndReported(): void
    {
        $region = self::region();
        $region->datamodel->meta = [$region->datamodel->meta[1]]; // only Ports

        $warnings = [];
        $merged = self::merger()->merge(self::generic(), $region, $warnings);

        $this->assertSame('Countries', $merged->datamodel->simulation_settings->SEL->country_border_layer);
        $this->assertContains('Layer reference "Countries" is not a layer of this config, kept as is.', $warnings);
    }

    /**
     * @throws \JsonException
     */
    public function testInputsAreNotModified(): void
    {
        $generic = self::generic();
        $region = self::region();
        $before = self::canonical([$generic, $region]);

        self::merger()->merge($generic, $region);

        $this->assertSameContents($before, self::canonical([$generic, $region]));
    }

    /**
     * @throws \JsonException
     */
    public function testMergedResultDoesNotShareObjectsWithTheInputs(): void
    {
        $generic = self::generic();
        $merged = self::merger()->merge($generic, self::region());

        $merged->datamodel->meta[0]->layer_type->{'0'}->displayName = 'changed afterwards';

        $this->assertSame('default', $generic->datamodel->meta[0]->layer_type->{'0'}->displayName);
    }

    /**
     * @dataProvider formats
     */
    public function testRegionFormatIsRecognised(string $json, bool $expected): void
    {
        $this->assertSame($expected, RegionConfigMerger::isRegionFormat(ConfigFactory::json($json)));
    }

    public static function formats(): iterable
    {
        yield 'complete config of the editor' =>
            [json_encode(ConfigFactory::config([ConfigFactory::layer('X', 'X')])), false];
        yield 'layer with a generic name' =>
            ['{"datamodel": {"meta": [{"msp_config_generic_name": "A", "layer_name": "X"}]}}', true];
        yield 'simulation_settings' => ['{"datamodel": {"meta": [], "simulation_settings": {}}}', true];
        yield 'nothing' => ['{}', false];
        yield 'no layers' => ['{"datamodel": {"meta": []}}', false];
    }

    /**
     * @throws \JsonException
     */
    public function testCompleteConfigIsReturnedInTheNewShape(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_Countries', 'Countries')]);
        $before = self::canonical($config);

        $merged = self::merger()->merge(self::emptyGeneric(), $config);

        $this->assertSameContents($before, self::canonical($config), 'the input is not modified');
        $this->assertSame(
            [
                'restrictions',
                'plans',
                'dependencies',
                'simulation_settings',
                'policy_settings',
                'meta',
                'expertise_definitions',
                'objectives',
                'edition_name',
                'region',
                'start',
                'end'
            ],
            self::datamodelKeys($merged),
            'simulation_settings takes the place of CEL, SEL and MEL'
        );
        $this->assertSameJson($config->datamodel->CEL, $merged->datamodel->simulation_settings->CEL);
        $this->assertSameJson($config->datamodel->SEL, $merged->datamodel->simulation_settings->SEL);
        $this->assertSameJson(
            $config->datamodel->MEL,
            $merged->datamodel->simulation_settings->MEL,
            'a complete config is not otherwise changed'
        );
        $this->assertSameJson($config->datamodel->meta, $merged->datamodel->meta);
        $this->assertSameJson($config->metadata, $merged->metadata);
    }

    public function testNullSelAndMelStayNullInTheNewShape(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X', 'X')], ['SEL' => null, 'MEL' => null]);

        $settings = self::merger()->merge(self::generic(), $config)->datamodel->simulation_settings;

        $this->assertTrue(ConfigValues::has($settings, 'SEL'));
        $this->assertNull($settings->SEL);
        $this->assertNull($settings->MEL);
        $this->assertNotNull($settings->CEL);
    }

    public function testConvertingTwiceChangesNothing(): void
    {
        $once = RegionConfigMerger::toSimulationSettings(ConfigFactory::config([ConfigFactory::layer('X', 'X')]));

        $twice = RegionConfigMerger::toSimulationSettings($once);

        $this->assertSame(
            json_encode($once, ConfigValues::ENCODE_FLAGS),
            json_encode($twice, ConfigValues::ENCODE_FLAGS)
        );
    }

    public function testOldStyleKeysAreLaidOverTheSameKeysOfAnExistingSimulationSettings(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X', 'X')]);
        $config->datamodel->simulation_settings = ConfigFactory::json(
            '{"CEL": {"own": true, "grey_centerpoint_color": "#000000FF"}}'
        );

        $settings = RegionConfigMerger::toSimulationSettings($config)->datamodel->simulation_settings;

        $this->assertTrue($settings->CEL->own, 'what only simulation_settings has stays');
        $this->assertSame('#3D1C04FF', $settings->CEL->grey_centerpoint_color, 'the old-style key wins');
        $this->assertTrue(ConfigValues::has($settings, 'SEL'));
        $this->assertTrue(ConfigValues::has($settings, 'MEL'));
    }

    public function testOldStyleKeysAreLaidOverTheGenericAndRegionSettingsKeyByKey(): void
    {
        $region = self::region();
        $region->datamodel->MEL = ConfigFactory::json('{"rows": 999}');
        $region->datamodel->CEL = ConfigFactory::json('{"b": 7}');
        $region->datamodel->SEL = ConfigFactory::json(
            '{"port_layers": [{"layer_name": "X_Mine", "port_type": "DefinedPort"}]}'
        );

        $warnings = [];
        $merged = self::merger()->merge(self::generic(), $region, $warnings);

        $settings = $merged->datamodel->simulation_settings;
        $this->assertSame(
            ['ecologyCategories', 'modelfile', 'rows'],
            array_keys(get_object_vars($settings->MEL)),
            'generic, then the region, then the old-style key'
        );
        $this->assertSame(999, $settings->MEL->rows);
        $this->assertSame('model.eiixml', $settings->MEL->modelfile);
        $this->assertSame(['a' => 1, 'b' => 7], get_object_vars($settings->CEL), "b: old-style 7 beats the region's 3");
        $this->assertSame(
            ['X_Mine'],
            array_map(static fn($port) => $port->layer_name, $settings->SEL->port_layers),
            'an old-style list replaces the list of the generic and region config'
        );
        $this->assertSame('X_Countries', $settings->SEL->country_border_layer, 'the rest still comes from them');
        $this->assertSame(20, $settings->SEL->output_configuration->pixels_per_mel_cell);
        $this->assertSame([], $warnings);
    }

    public function testOldStyleKeysAreNotPartOfTheResult(): void
    {
        $region = self::region();
        foreach (['CEL', 'SEL', 'MEL'] as $name) {
            $region->datamodel->{$name} = new \stdClass();
        }

        $keys = self::datamodelKeys(self::merger()->merge(self::generic(), $region));

        $this->assertSame(
            ['meta', 'restrictions', 'simulation_settings', 'plans', 'edition_name', 'dependencies'],
            $keys
        );
    }

    public function testAnOldStyleNullMeansNoSimulation(): void
    {
        $region = self::region();
        $region->datamodel->SEL = null;

        $settings = self::merger()->merge(self::generic(), $region)->datamodel->simulation_settings;

        $this->assertTrue(ConfigValues::has($settings, 'SEL'));
        $this->assertNull($settings->SEL);
        $this->assertNotNull($settings->MEL, 'the others are not affected');
    }

    public function testAnOldStyleKeyBeatsAnExplicitNullOfTheRegion(): void
    {
        $region = self::region();
        $region->datamodel->simulation_settings->MEL = null;
        $region->datamodel->MEL = ConfigFactory::json('{"rows": 5}');

        $settings = self::merger()->merge(self::generic(), $region)->datamodel->simulation_settings;

        $this->assertSame(['rows' => 5], get_object_vars($settings->MEL));
    }

    public function testACompleteConfigIsMergedWithTheGenericConfigUnderneath(): void
    {
        $config = ConfigFactory::config(
            [ConfigFactory::layer('X_Countries', 'Countries'), ConfigFactory::layer('X_Ports', 'Ports')],
            ['restrictions' => new \stdClass()]
        );
        $config->datamodel->SEL->output_configuration = ConfigFactory::json('{"pixels_per_mel_cell": 30}');
        $config->datamodel->SEL->port_layers = [ConfigFactory::json('{"layer_name": "X_Ports", "port_type": "Mine"}')];
        unset($config->datamodel->dependencies);

        $merged = self::merger()->merge(self::generic(), $config);

        $sel = $merged->datamodel->simulation_settings->SEL;
        $this->assertEquals(
            ['pixels_per_mel_cell' => 30, 'simulation_area_cell_size' => 5000],
            get_object_vars($sel->output_configuration),
            "the config's own value wins, the generic config supplies what it lacks"
        );
        $this->assertSame(
            [['layer_name' => 'X_Ports', 'port_type' => 'Mine']],
            array_map('get_object_vars', $sel->port_layers),
            'the old-style list of the config is the list'
        );
        $this->assertSame(
            [],
            get_object_vars($merged->datamodel->restrictions),
            'no restrictions of the generic config'
        );
        $this->assertSameJson(self::generic()->datamodel->dependencies, $merged->datamodel->dependencies);
        $this->assertSame(
            ['X_Countries', 'X_Ports'],
            array_map(static fn($layer) => $layer->layer_name, $merged->datamodel->meta)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testRealCompleteConfigsStayCompleteWhenMergedWithTheGenericConfig(): void
    {
        [$result] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $merged = self::merger()->merge($result->generic, $original);

            // a complete config has all its own values: the generic config underneath adds nothing, and only
            // the shape changes (restriction map keys and the order of lists are not compared)
            $this->assertSame(
                [],
                self::comparator()->differences(RegionConfigMerger::toSimulationSettings($original), $merged),
                "$id: a complete config has to stay what it was, in the new shape"
            );
            $this->assertFalse(ConfigValues::has($merged->datamodel, 'SEL'), $id);
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealRegionConfigsMergeBackToTheOriginals(): void
    {
        [$result] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame([], self::differencesToOriginal($result->generic, $result->regions[$id], $original), $id);
        }
    }
}
