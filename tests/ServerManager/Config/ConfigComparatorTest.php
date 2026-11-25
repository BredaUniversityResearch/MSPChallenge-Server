<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\RegionConfigMerger;

class ConfigComparatorTest extends ConfigTestCase
{
    private static function config(): \stdClass
    {
        $property = static fn(string $name) => ['property_name' => $name, 'enabled' => 1];
        $config = ConfigFactory::config([
            ConfigFactory::layer(
                'A_Countries',
                'Countries',
                ['layer_info_properties' => [$property('id'), $property('name')]]
            ),
            ConfigFactory::layer('A_Ports', 'Ports', ['layer_tags' => ['Point', 'Port']]),
        ], [
            'restrictions' => ConfigFactory::restrictions([
                ConfigFactory::restriction('A_Countries', 'A_Ports'),
                ConfigFactory::restriction('A_Ports', 'A_Countries', 'Other'),
            ]),
        ]);
        $config->datamodel->SEL->port_layers = ConfigFactory::objects([
            ['layer_name' => 'A_Ports', 'port_type' => 'DefinedPort'],
            ['layer_name' => 'A_Countries', 'port_type' => 'MaintenanceDestination'],
        ]);
        return RegionConfigMerger::toSimulationSettings($config);
    }

    /**
     * @throws \JsonException
     */
    private function differences(\stdClass $expected, \stdClass $actual, int $limit = 20): array
    {
        return self::comparator()->differences($expected, $actual, $limit);
    }

    /**
     * @throws \JsonException
     */
    public function testEqualConfigsHaveNoDifferences(): void
    {
        $this->assertSame([], $this->differences(self::config(), self::config()));
    }

    /**
     * @throws \JsonException
     */
    public function testKeyOrderAndNumberTypeAreIgnored(): void
    {
        $actual = self::config();
        $actual->datamodel->simulation_settings->SEL->maintenance_destinations->base_intensity = 5; // was 5.0
        $actual->datamodel->meta[0] = ConfigFactory::objects(array_reverse(
            get_object_vars($actual->datamodel->meta[0]),
            true
        ));

        $this->assertSame([], $this->differences(self::config(), $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testAdditiveListsAreComparedWithoutRegardToOrder(): void
    {
        $actual = self::config();
        $actual->datamodel->simulation_settings->SEL->port_layers = array_reverse(
            $actual->datamodel->simulation_settings->SEL->port_layers
        );
        $actual->datamodel->meta[0]->layer_info_properties = array_reverse(
            $actual->datamodel->meta[0]->layer_info_properties
        );
        $actual->datamodel->restrictions = ConfigFactory::restrictions(array_reverse(
            \App\Domain\Config\Merge\LayerReferences::flattenRestrictions($actual->datamodel->restrictions)
        ));

        $this->assertSame([], $this->differences(self::config(), $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testOtherListsMustKeepTheirOrder(): void
    {
        $actual = self::config();
        $actual->datamodel->meta[1]->layer_tags = ['Port', 'Point'];

        $this->assertSame(['layer A_Ports: layer_tags differs'], $this->differences(self::config(), $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testTheOrderOfTheLayersMatters(): void
    {
        $actual = self::config();
        $actual->datamodel->meta = array_reverse($actual->datamodel->meta);

        $differences = $this->differences(self::config(), $actual);

        $this->assertNotEmpty($differences);
        $this->assertStringContainsString('layer A_Countries', $differences[0]);
    }

    /**
     * @throws \JsonException
     */
    public function testMissingAndUnexpectedItemsAreCounted(): void
    {
        $actual = self::config();
        $actual->datamodel->simulation_settings->SEL->port_layers = [
            $actual->datamodel->simulation_settings->SEL->port_layers[0],
            ConfigFactory::objects(['layer_name' => 'Elsewhere', 'port_type' => 'DefinedPort']),
        ];
        $actual->datamodel->meta[0]->layer_info_properties = [];

        $this->assertEqualsCanonicalizing([
            'layer A_Countries: layer_info_properties: 2 item(s) missing, 0 unexpected',
            'SEL.port_layers: 1 item(s) missing, 1 unexpected',
        ], $this->differences(self::config(), $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testMissingRestrictionIsReported(): void
    {
        $actual = self::config();
        $actual->datamodel->restrictions = new \stdClass();

        $this->assertSame(
            ['datamodel.restrictions: 2 item(s) missing, 0 unexpected'],
            $this->differences(self::config(), $actual)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testMissingAndNullSimulationsAreTheSame(): void
    {
        $expected = self::config();
        $expected->datamodel->simulation_settings->MEL = null;
        $actual = self::config();
        unset($actual->datamodel->simulation_settings->MEL);

        $this->assertSame([], $this->differences($expected, $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testChangedSimulationValuesAreReported(): void
    {
        $actual = self::config();
        $actual->datamodel->simulation_settings->CEL->grey_centerpoint_sprite = 'other';
        $actual->datamodel->simulation_settings->SEL->country_border_layer = 'X';

        $this->assertEqualsCanonicalizing(
            ['simulation_settings.CEL differs', 'SEL.country_border_layer differs'],
            $this->differences(self::config(), $actual)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testChangedLayerFieldAndMetadataAreReported(): void
    {
        $actual = self::config();
        $actual->datamodel->meta[0]->layer_tooltip = 'changed';
        $actual->metadata->config_version = '3.0.0';

        $this->assertEqualsCanonicalizing(
            ['metadata differs', 'layer A_Countries: layer_tooltip differs'],
            $this->differences(self::config(), $actual)
        );
    }

    /**
     * @throws \JsonException
     */
    public function testMissingTopLevelKeysAreReported(): void
    {
        $actual = self::config();
        unset($actual->datamodel->plans);

        $this->assertSame(['datamodel.plans is missing after merging'], $this->differences(self::config(), $actual));
    }

    /**
     * @throws \JsonException
     */
    public function testNumberOfReportedDifferencesIsLimited(): void
    {
        $actual = self::config();
        foreach (['layer_tooltip', 'layer_short', 'layer_category', 'layer_depth', 'layer_green'] as $key) {
            $actual->datamodel->meta[0]->{$key} = 'x';
        }

        $this->assertCount(3, $this->differences(self::config(), $actual, 3));
    }
}
