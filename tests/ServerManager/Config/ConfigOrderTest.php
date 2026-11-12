<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\ConfigNormalizer;
use App\Domain\Config\Merge\ConfigOrder;

/**
 * The order of the keys of a final config and of a normalized config: alphabetical, with the layers as the one
 * exception, and lists as they are.
 */
class ConfigOrderTest extends ConfigTestCase
{
    /**
     * @return string[]
     */
    private static function keys(mixed $object): array
    {
        return array_map('strval', array_keys(get_object_vars($object)));
    }

    public function testObjectKeysAreInAlphabeticalOrderWithoutRegardToCase(): void
    {
        $config = ConfigFactory::json('{"b": 1, "B2": 2, "a": {"z": 1, "Y": 2, "x": 3}, "A": 4}');

        $ordered = ConfigOrder::config($config);

        $this->assertSame(
            ['A', 'a', 'b', 'B2'],
            self::keys($ordered),
            'a tie of two spellings: the upper case one first'
        );
        $this->assertSame(['x', 'Y', 'z'], self::keys($ordered->a), 'also in the objects below');
    }

    public function testKeysThatAreOnlyDigitsComeFirstInNumberOrder(): void
    {
        $config = ConfigFactory::json(
            '{"datamodel": {"meta": [], "layer_type": {"10": 1, "2": 2, "a": 3, "0": 4, "1": 5}}}'
        );

        $ordered = ConfigOrder::config($config);

        $this->assertSame(['0', '1', '2', '10', 'a'], self::keys($ordered->datamodel->layer_type));
    }

    public function testALayerStartsWithItsGenericNameThenTheFixedKeysThenTheRestAlphabetically(): void
    {
        $config = ConfigFactory::json(
            '{"datamodel": {"meta": [{"layer_tags": [], "zeta": 1, "layer_name": "A", "msp_config_generic_name": "G",'
            . ' "Alpha": {"b": 1, "a": 2}, "layer_geotype": "polygon"}]}}'
        );

        $layer = ConfigOrder::config($config)->datamodel->meta[0];

        $this->assertSame(
            ['msp_config_generic_name', 'layer_name', 'layer_geotype', 'layer_tags', 'Alpha', 'zeta'],
            self::keys($layer)
        );
        $this->assertSame(['a', 'b'], self::keys($layer->Alpha), 'the objects in a layer are in order too');
    }

    public function testListsKeepTheirOrderButTheObjectsInThemAreOrdered(): void
    {
        $config = ConfigFactory::json(
            '{"datamodel": {"meta": [], "restrictions": {"x|y": [{"startlayer": "b", "endlayer": "a"},'
            . ' {"startlayer": "a", "endlayer": "c"}]}}}'
        );

        $list = ConfigOrder::config($config)->datamodel->restrictions->{'x|y'};

        $this->assertSame(['b', 'a'], array_column(array_map('get_object_vars', $list), 'startlayer'), 'the list');
        $this->assertSame(['endlayer', 'startlayer'], self::keys($list[0]), 'the object in the list');
    }

    public function testTheLayersOfAConfigKeepTheirOrder(): void
    {
        $config = ConfigFactory::config([
            ConfigFactory::layer('X_Zebra', 'Zebra'),
            ConfigFactory::layer('X_Antelope', 'Antelope'),
        ]);

        $ordered = ConfigOrder::config($config);

        $this->assertSame(
            ['X_Zebra', 'X_Antelope'],
            array_map(static fn(\stdClass $layer) => $layer->layer_name, $ordered->datamodel->meta)
        );
    }

    public function testTheConfigThatIsGivenIsNotChanged(): void
    {
        $config = ConfigFactory::json('{"b": {"y": 1, "x": 2}, "a": 1}');
        $before = json_encode($config);

        ConfigOrder::config($config);

        $this->assertSame($before, json_encode($config));
    }

    public function testOrderingTwiceGivesTheSameResult(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_A', 'A', ['zz' => 1, 'layer_tooltip' => 'x'])]);

        $once = ConfigOrder::config($config);

        $this->assertSame(json_encode($once), json_encode(ConfigOrder::config($once)));
    }

    public function testANewSimulationFindsItsPlaceByItsName(): void
    {
        $config = ConfigFactory::json(
            '{"datamodel": {"meta": [], "simulation_settings": {"SEL": null, "Zed": {"a": 1}, "CEL": null, "Abc": null,'
            . ' "MEL": null}}}'
        );

        $settings = ConfigOrder::config($config)->datamodel->simulation_settings;

        $this->assertSame(['Abc', 'CEL', 'MEL', 'SEL', 'Zed'], self::keys($settings));
    }

    public function testTheNormalizerGivesAConfigTheOrderOfAFinalConfig(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_A', 'A', ['layer_information' => 'text'])]);

        $normalized = new ConfigNormalizer()->normalize($config);

        $datamodel = self::keys($normalized->datamodel);
        $sorted = $datamodel;
        usort($sorted, ConfigOrder::compareKeys(...));
        $this->assertSame($sorted, $datamodel, 'the keys of the datamodel are in order');
        $this->assertContains('simulation_settings', $datamodel);
        $this->assertNotContains('CEL', $datamodel, 'the old place of the simulations');
        $this->assertSame(['CEL', 'MEL', 'SEL'], self::keys($normalized->datamodel->simulation_settings));
        $layer = $normalized->datamodel->meta[0];
        $this->assertFalse(property_exists($layer, 'layer_information'), 'a documented removal');
        $this->assertSame('layer_name', self::keys($layer)[0]);
        $again = new ConfigNormalizer()->normalize($normalized);
        $this->assertSame(json_encode($normalized), json_encode($again), 'normalizing again changes nothing');
    }
}
