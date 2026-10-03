<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\StripResult;
use App\Domain\Config\Split\ConfigValues;
use App\Domain\Config\Split\GenericNameRegistry;

class RegionConfigStripperTest extends ConfigTestCase
{
    /**
     * Three complete configs that share a lot: layers, one restriction, port layers, dependencies, CEL, SEL
     * values and the MEL categories. Returns [generic, registry, configs].
     *
     * @return array{0: \stdClass, 1: GenericNameRegistry, 2: array<string, \stdClass>}
     */
    private static function fixture(): array
    {
        $configs = [];
        foreach (['A', 'B', 'C'] as $prefix) {
            $property = ConfigFactory::objects(['property_name' => 'id', 'enabled' => 1]);
            $config = ConfigFactory::config([
                ConfigFactory::layer($prefix . '_Countries', 'Countries', ['layer_info_properties' => [$property]]),
                ConfigFactory::layer($prefix . '_Ports', 'Ports'),
                ConfigFactory::layer($prefix . '_Wind', 'Wind Farms'),
            ], [
                'restrictions' =>
                    ConfigFactory::restrictions(
                        [ConfigFactory::restriction($prefix . '_Countries', $prefix . '_Ports')]
                    ),
            ]);
            $config->datamodel->MEL->rows = ord($prefix); // differs per config
            $sel = $config->datamodel->SEL;
            $sel->country_border_layer = $prefix . '_Countries';
            $sel->shipping_lane_layers = [$prefix . '_Ports', $prefix . '_Wind'];
            $sel->port_layers = ConfigFactory::objects([
                ['layer_name' => $prefix . '_Ports', 'port_type' => 'DefinedPort'],
                ['layer_name' => $prefix . '_Wind', 'port_type' => 'MaintenanceDestination'],
            ]);
            $configs[strtolower($prefix) . '/' . strtolower($prefix)] = $config;
        }
        [$result, $registry] = self::split($configs);
        return [$result->generic, $registry, $configs];
    }

    /**
     * @return array{0: \stdClass, 1: GenericNameRegistry, 2: \stdClass} generic, registry, a copy of config A
     */
    private static function custom(): array
    {
        [$generic, $registry, $configs] = self::fixture();
        return [$generic, $registry, ConfigFactory::copy($configs['a/a'])];
    }

    /**
     * @throws \JsonException
     */
    private function assertStrippedAndLossless(StripResult $result, \stdClass $generic, \stdClass $original): \stdClass
    {
        $this->assertTrue($result->isStripped(), implode(' | ', $result->reasons));
        $this->assertSame(
            [],
            self::differencesToOriginal($generic, $result->region, $original),
            'merging the stripped config must give the original back'
        );
        return $result->region;
    }

    /**
     * @throws \JsonException
     */
    public function testStrippingAndMergingGivesTheConfigBack(): void
    {
        [$generic, $registry, $configs] = self::fixture();

        foreach ($configs as $id => $config) {
            $result = self::stripper()->strip($generic, $config, $registry);
            $this->assertStrippedAndLossless($result, $generic, $config);
            $this->assertSame(3, $result->stats['layers inherited'], $id);
            $this->assertSame(0, $result->stats['layers standalone'], $id);
        }
    }

    public function testNothingTheGenericConfigHasIsLeftInTheRegionConfig(): void
    {
        [$generic, $registry, $configs] = self::fixture();

        $region = self::stripper()->strip($generic, $configs['a/a'], $registry)->region;

        foreach ($region->datamodel->meta as $entry) {
            $this->assertSame(
                [
                    'msp_config_generic_name',
                    'layer_name',
                    'layer_download_from_geoserver',
                    'layer_raster_minimum_value_cutoff'
                ],
                array_keys(get_object_vars($entry))
            );
        }
        $this->assertFalse(ConfigValues::has($region->datamodel, 'restrictions'));
        $this->assertFalse(ConfigValues::has($region->datamodel, 'dependencies'));
        $simulation = $region->datamodel->simulation_settings;
        $this->assertFalse(ConfigValues::has($simulation, 'CEL'));
        $this->assertSame(
            ['port_intensity'],
            array_keys(get_object_vars($simulation->SEL)),
            'only what is region specific'
        );
        $this->assertSame(['modelfile', 'rows'], array_keys(get_object_vars($simulation->MEL)));
    }

    /**
     * @throws \JsonException
     */
    public function testStrippingIsIdempotent(): void
    {
        [$generic, $registry, $configs] = self::fixture();
        $first = self::stripper()->strip($generic, $configs['b/b'], $registry);

        $again = self::stripper()->strip($generic, $first->region, $registry);

        $this->assertTrue($again->isStripped());
        $this->assertSameJson($first->region, $again->region);
    }

    public function testARegionConfigIsExpandedBeforeItIsStrippedAgain(): void
    {
        [$generic, $registry, $configs] = self::fixture();
        [$split] = self::split($configs);

        $fromComplete = self::stripper()->strip($generic, $configs['a/a'], $registry);
        $fromRegion = self::stripper()->strip($generic, $split->regions['a/a'], $registry);

        $this->assertSameJson($fromComplete->region, $fromRegion->region);
    }

    public function testAnUnknownLayerIsKeptAsAStandaloneEntry(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta[] = ConfigFactory::layer(
            'MY_CUSTOM_LAYER',
            'My Custom Layer',
            ['layer_tooltip' => 'mine']
        );
        $config->datamodel->restrictions = ConfigFactory::restrictions(array_merge(
            \App\Domain\Config\Merge\LayerReferences::flattenRestrictions($config->datamodel->restrictions),
            [ConfigFactory::restriction('MY_CUSTOM_LAYER', 'A_Ports', 'custom')]
        ));

        $result = self::stripper()->strip($generic, $config, $registry);

        $region = $this->assertStrippedAndLossless($result, $generic, $config);
        $this->assertSame(3, $result->stats['layers inherited']);
        $this->assertSame(1, $result->stats['layers standalone']);
        $custom = $region->datamodel->meta[3];
        $this->assertFalse(ConfigValues::has($custom, 'msp_config_generic_name'), 'nothing generic to match');
        $this->assertSame('MY_CUSTOM_LAYER', $custom->layer_name);
        $this->assertSame('mine', $custom->layer_tooltip);
        $this->assertSame(
            'layer_geotype',
            array_keys(get_object_vars($custom))[1],
            'a standalone entry has all its fields'
        );
        $this->assertSame(['MY_CUSTOM_LAYER|Ports'], array_keys(get_object_vars($region->datamodel->restrictions)));
    }

    /**
     * @throws \JsonException
     */
    public function testALayerNamedLikeAGenericLayerGetsAnAliasSoReferencesStayUnambiguous(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta[] = ConfigFactory::layer('Countries', 'Unique Thing'); // 'Countries' is generic

        $result = self::stripper()->strip($generic, $config, $registry);

        $region = $this->assertStrippedAndLossless($result, $generic, $config);
        $custom = $region->datamodel->meta[3];
        $this->assertSame('Countries', $custom->layer_name);
        $this->assertSame('Countries_custom', $custom->msp_config_generic_name, 'the one case where it needs a name');
    }

    public function testAnAlreadyExpandedConfigIsNotMergedWithTheGenericConfigAgain(): void
    {
        [$generic, $registry, $configs] = self::fixture();
        $older = ConfigFactory::copy($generic);
        unset($older->datamodel->simulation_settings->SEL->ship_types);
        $region = self::stripper()->strip($older, $configs['a/a'], $registry)->region;
        $effective = self::merger()->merge($older, $region);

        $result = self::stripper()->strip($generic, $effective, $registry, true);

        $this->assertTrue($result->isStripped(), implode(' | ', $result->reasons));
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize($effective),
            self::merger()->merge($generic, $result->region)
        ));
        $this->assertFalse(
            ConfigValues::has($result->region->datamodel->simulation_settings->SEL, 'ship_types'),
            'ship_types is generic now'
        );
    }

    public function testTheStrippedConfigNamesItsParentAndTheFinalConfigDoesNot(): void
    {
        [$generic, $registry, $config] = self::custom();

        $result = self::stripper()->strip($generic, $config, $registry, false, 'public');

        $region = $this->assertStrippedAndLossless($result, $generic, $config);
        $this->assertSame('public', $region->metadata->parent);
        $this->assertFalse(ConfigValues::has($config->metadata ?? new \stdClass(), 'parent'), 'input unchanged');
        $final = self::merger()->merge($generic, $region);
        $this->assertFalse(ConfigValues::has($final->metadata, 'parent'));
    }

    public function testWithoutAParentNameTheMetadataIsTheOneOfTheConfig(): void
    {
        [$generic, $registry, $config] = self::custom();

        $result = self::stripper()->strip($generic, $config, $registry);

        $region = $this->assertStrippedAndLossless($result, $generic, $config);
        $this->assertFalse(ConfigValues::has($region->metadata ?? new \stdClass(), 'parent'));
    }

    public function testRasterLayersKeepTheirWidthAndHeightWhenAConfigIsStripped(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta[] = ConfigFactory::layer('MY_RASTER', 'My Raster', [
            'layer_geotype' => 'raster',
            'layer_width' => 300,
            'layer_height' => 200,
        ]);
        $config->datamodel->meta[] = ConfigFactory::layer('MY_POLYGON', 'My Polygon'); // width and height 1024

        $result = self::stripper()->strip($generic, $config, $registry);
        $region = $this->assertStrippedAndLossless($result, $generic, $config);

        $raster = $region->datamodel->meta[3];
        $polygon = $region->datamodel->meta[4];
        $this->assertSame(300, $raster->layer_width);
        $this->assertSame(200, $raster->layer_height);
        $this->assertFalse(ConfigValues::has($polygon, 'layer_width'));
        $this->assertFalse(ConfigValues::has($polygon, 'layer_height'));
    }

    public function testChangedValuesBecomeSmallOverrides(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta[0]->layer_tooltip = 'my own tooltip';
        $config->datamodel->meta[0]->layer_info_properties[] = ConfigFactory::objects(
            ['property_name' => 'extra', 'enabled' => 0]
        );
        $config->datamodel->SEL->ship_types[0]->ship_type_name = 'Ferry (mine)';
        $config->datamodel->CEL->grey_centerpoint_sprite = 'mine';

        $region = $this->assertStrippedAndLossless(
            self::stripper()->strip($generic, $config, $registry),
            $generic,
            $config
        );

        $entry = $region->datamodel->meta[0];
        $this->assertSame('my own tooltip', $entry->layer_tooltip);
        $this->assertSame(
            ['extra'],
            array_map(static fn($p) => $p->property_name, $entry->layer_info_properties),
            'only the added property'
        );
        $this->assertSame(
            ['grey_centerpoint_sprite' => 'mine'],
            get_object_vars($region->datamodel->simulation_settings->CEL)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testALayerThatLacksKeysOfItsGenericLayerIsKeptStandaloneAndReported(): void
    {
        [$generic, $registry, $config] = self::custom();
        unset($config->datamodel->meta[2]->layer_type->{'1'}); // a region can only add, never remove

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertStrippedAndLossless($result, $generic, $config);
        $this->assertSame(1, $result->stats['layers standalone']);
        $this->assertFalse(ConfigValues::has($result->region->datamodel->meta[2], 'msp_config_generic_name'));
        $this->assertSame('A_Wind', $result->region->datamodel->meta[2]->layer_name);
        $this->assertNotEmpty(array_filter(
            $result->warnings,
            static fn($w) => str_contains($w, 'Layer A_Wind is kept standalone')
        ));
    }

    /**
     * @throws \JsonException
     */
    public function testRemovingALayerNeedsNoExclusions(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta = array_values(array_filter(
            $config->datamodel->meta,
            static fn($l) => $l->layer_name !== 'A_Wind'
        ));
        $config->datamodel->SEL->shipping_lane_layers = ['A_Ports'];
        $config->datamodel->SEL->port_layers = [$config->datamodel->SEL->port_layers[0]];

        $region = $this->assertStrippedAndLossless(
            self::stripper()->strip($generic, $config, $registry),
            $generic,
            $config
        );

        $this->assertCount(2, $region->datamodel->meta);
    }

    /**
     * @throws \JsonException
     */
    public function testRestrictionsOfTheGenericConfigAreNotRepeated(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->restrictions = ConfigFactory::restrictions([
            ConfigFactory::restriction('A_Countries', 'A_Ports'),
            ConfigFactory::restriction('A_Countries', 'A_Wind', 'mine'),
        ]);

        $region = $this->assertStrippedAndLossless(
            self::stripper()->strip($generic, $config, $registry),
            $generic,
            $config
        );

        $this->assertSame(['Countries|WindFarms'], array_keys(get_object_vars($region->datamodel->restrictions)));
    }

    public function testRefusesAConfigThatLacksAnItemTheGenericConfigWouldAdd(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->SEL->port_layers = [$config->datamodel->SEL->port_layers[0]]; // Wind is still a layer

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertFalse($result->isStripped());
        $this->assertNull($result->region);
        $this->assertStringContainsString('SEL.port_layers', $result->reasons[0]);
    }

    public function testRefusesAConfigThatLacksARestrictionOfTheGenericConfig(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->restrictions = new \stdClass();

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertFalse($result->isStripped());
        $this->assertStringContainsString('restriction', $result->reasons[0]);
    }

    public function testAConfigWithoutDependenciesInheritsTheOnesOfTheGenericConfig(): void
    {
        [$generic, $registry, $config] = self::custom();
        unset($config->datamodel->dependencies);

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertTrue($result->isStripped(), implode(' | ', $result->reasons));
        $this->assertFalse(ConfigValues::has($result->region->datamodel, 'dependencies'));
        $merged = self::merger()->merge($generic, $result->region);
        $this->assertSameJson($generic->datamodel->dependencies, $merged->datamodel->dependencies);
    }

    public function testAConfigWithoutASettingOfTheGenericConfigInheritsIt(): void
    {
        [$generic, $registry, $config] = self::custom();
        unset($config->datamodel->SEL->output_configuration);

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertTrue($result->isStripped(), implode(' | ', $result->reasons));
        $this->assertFalse(
            ConfigValues::has($result->region->datamodel->simulation_settings->SEL, 'output_configuration')
        );
        $merged = self::merger()->merge($generic, $result->region);
        $this->assertSameJson(
            $generic->datamodel->simulation_settings->SEL->output_configuration,
            $merged->datamodel->simulation_settings->SEL->output_configuration
        );
    }

    public function testOldStyleKeysAreAppliedAndGoneWhenAConfigIsStripped(): void
    {
        [$generic, $registry, $config] = self::custom();
        // new style and old style at once: the old-style MEL (rows 65) wins over simulation_settings (rows 1)
        $config->datamodel->simulation_settings = ConfigFactory::json('{"MEL": {"rows": 1}}');

        $result = self::stripper()->strip($generic, $config, $registry);

        $this->assertTrue($result->isStripped(), implode(' | ', $result->reasons));
        foreach (['CEL', 'SEL', 'MEL'] as $name) {
            $this->assertFalse(ConfigValues::has($result->region->datamodel, $name), "$name is not top-level any more");
        }
        $this->assertSame(65, $result->region->datamodel->simulation_settings->MEL->rows);
        $this->assertSame(
            65,
            self::merger()->merge($generic, $result->region)->datamodel->simulation_settings->MEL->rows
        );
    }

    public function testAConfigWithoutSelAndMelKeepsExplicitNulls(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->SEL = null;
        $config->datamodel->MEL = null;

        $region = $this->assertStrippedAndLossless(
            self::stripper()->strip($generic, $config, $registry),
            $generic,
            $config
        );

        $simulation = $region->datamodel->simulation_settings;
        $this->assertNull($simulation->SEL);
        $this->assertNull($simulation->MEL);
    }

    /**
     * @throws \JsonException
     */
    public function testAConfigIsStrippedAgainstAnEmptyGenericConfig(): void
    {
        [, $registry, $config] = self::custom();
        $empty = ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');

        $result = self::stripper()->strip($empty, $config, $registry);

        $region = $this->assertStrippedAndLossless($result, $empty, $config);
        $this->assertSame(3, $result->stats['layers standalone']);
        $this->assertSame(0, $result->stats['layers inherited']);
        $this->assertSame('MELdata/model.eiixml', $region->datamodel->simulation_settings->MEL->modelfile);
    }

    /**
     * @throws \JsonException
     */
    public function testReEvaluatingAgainstAnExtendedGenericConfigShrinksTheRegionConfigs(): void
    {
        [$generic, $registry, $configs] = self::fixture();
        $older = ConfigFactory::copy($generic);
        foreach (['ship_types', 'output_configuration', 'maintenance_destinations'] as $key) {
            unset($older->datamodel->simulation_settings->SEL->{$key});
        }
        unset($older->datamodel->simulation_settings->CEL);

        $sizeBefore = 0;
        $sizeAfter = 0;
        foreach ($configs as $id => $config) {
            $oldRegion = self::stripper()->strip($older, $config, $registry)->region;
            $newRegion = self::stripper()->strip($generic, $oldRegion, $registry);

            $this->assertTrue($newRegion->isStripped(), $id);
            $this->assertSame([], self::differencesToOriginal(
                $generic,
                $newRegion->region,
                $config
            ), "$id keeps its meaning");
            $sizeBefore += self::byteSize($oldRegion);
            $sizeAfter += self::byteSize($newRegion->region);
        }
        $this->assertLessThan($sizeBefore, $sizeAfter);
    }

    /**
     * @throws \JsonException
     */
    public function testStrippingDoesNotModifyItsInputs(): void
    {
        [$generic, $registry, $config] = self::custom();
        $config->datamodel->meta[0]->layer_tooltip = 'changed';
        $before = self::canonical([$generic, $config]);

        self::stripper()->strip($generic, $config, $registry);

        $this->assertSameContents($before, self::canonical([$generic, $config]));
    }

    /**
     * @throws \JsonException
     */
    public function testRealConfigsAreStrippedLosslessly(): void
    {
        [$split, $registry] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $result = self::stripper()->strip($split->generic, $original, $registry);

            $this->assertTrue($result->isStripped(), "$id: " . implode(' | ', $result->reasons));
            $this->assertSame([], self::differencesToOriginal($split->generic, $result->region, $original), $id);
            $this->assertSame(
                count($original->datamodel->meta),
                $result->stats['layers inherited'] + $result->stats['layers standalone'],
                $id
            );
            $this->assertGreaterThan(0, $result->stats['layers inherited'], "$id shares layers with the others");
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealStrippedConfigsAreNotLargerThanTheSplitterOutput(): void
    {
        [$split, $registry] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $region = self::stripper()->strip($split->generic, $original, $registry)->region;

            $this->assertLessThanOrEqual(self::byteSize($split->regions[$id]), self::byteSize($region), $id);
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealStrippingIsIdempotentAndStableOnTheSplitterOutput(): void
    {
        [$split, $registry] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $first = self::stripper()->strip($split->generic, $original, $registry)->region;
            $again = self::stripper()->strip($split->generic, $first, $registry);
            $fromSplitter = self::stripper()->strip($split->generic, $split->regions[$id], $registry);

            $this->assertTrue($again->isStripped() && $fromSplitter->isStripped(), $id);
            $this->assertSameJson($first, $again->region, "$id: idempotent");
            $this->assertSameJson(
                $first,
                $fromSplitter->region,
                "$id: the splitter output strips to the same region config"
            );
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRealRegionConfigsShrinkWhenTheGenericConfigIsExtended(): void
    {
        [$split, $registry] = self::realSplit();
        $generic = $split->generic;
        $sel = $generic->datamodel->simulation_settings->SEL ?? null;
        foreach (['ship_types', 'heatmap_bleed_config', 'maintenance_destinations', 'output_configuration'] as $key) {
            if (!$sel instanceof \stdClass || !ConfigValues::has($sel, $key)) {
                $this->markTestSkipped("The generic config has no SEL.$key to take out.");
            }
        }
        $older = ConfigFactory::copy($generic);
        foreach (['ship_types', 'heatmap_bleed_config', 'maintenance_destinations', 'output_configuration'] as $key) {
            unset($older->datamodel->simulation_settings->SEL->{$key});
        }

        $sizeBefore = 0;
        $sizeAfter = 0;
        foreach (self::realConfigs() as $id => $original) {
            $oldRegion = self::stripper()->strip($older, $original, $registry);
            $this->assertTrue($oldRegion->isStripped(), $id);
            $newRegion = self::stripper()->strip($generic, $oldRegion->region, $registry);
            $this->assertTrue($newRegion->isStripped(), $id);
            $this->assertSame([], self::differencesToOriginal($generic, $newRegion->region, $original), $id);
            $this->assertLessThanOrEqual(self::byteSize($oldRegion->region), self::byteSize($newRegion->region), $id);
            $sizeBefore += self::byteSize($oldRegion->region);
            $sizeAfter += self::byteSize($newRegion->region);
        }
        $this->assertLessThan($sizeBefore, $sizeAfter);
    }
}
