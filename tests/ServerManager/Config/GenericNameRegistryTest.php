<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Split\GenericNameRegistry;

class GenericNameRegistryTest extends ConfigTestCase
{
    private function registry(array $configs): GenericNameRegistry
    {
        $registry = new GenericNameRegistry();
        $registry->register($configs);
        return $registry;
    }

    public function testNameIsThePascalCaseOfLayerShort(): void
    {
        $registry = $this->registry(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('NS_Wind', 'Wind Farms (Simulated)'),
        ])]);
        $this->assertSame('WindFarmsSimulated', $registry->get('NS_Wind'));
    }

    public function testPlayAreaLayersAlwaysGetTheNameFromTheDesignDocument(): void
    {
        $registry = $this->registry([
            'a/a' => ConfigFactory::config([ConfigFactory::layer('_PLAYAREA_NS', '')]),
            'b/b' => ConfigFactory::config([ConfigFactory::layer('_PLAYAREAWESTBS', '')]),
            'c/c' => ConfigFactory::config([ConfigFactory::layer('_PLAYAREA_X', 'Some Other Short')]),
        ]);
        $this->assertSame('PlayArea', $registry->get('_PLAYAREA_NS'));
        $this->assertSame('PlayArea', $registry->get('_PLAYAREAWESTBS'));
        $this->assertSame('SomeOtherShort', $registry->get('_PLAYAREA_X'), 'a layer_short takes precedence');
    }

    public function testEmptyLayerShortFallsBackToTheNameWithoutRegionPrefix(): void
    {
        $registry = $this->registry(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('NS_Some_Layer', ''),
        ])]);
        $this->assertSame('SomeLayer', $registry->get('NS_Some_Layer'));
    }

    public function testLayersOfDifferentConfigsWithTheSameShortShareOneName(): void
    {
        $registry = $this->registry([
            'a/a' => ConfigFactory::config([ConfigFactory::layer('A_Countries', 'Countries')]),
            'b/b' => ConfigFactory::config([ConfigFactory::layer('B_Countries', 'Countries')]),
        ]);
        $this->assertSame('Countries', $registry->get('A_Countries'));
        $this->assertSame('Countries', $registry->get('B_Countries'));
        $this->assertSame(['Countries' => ['A_Countries', 'B_Countries']], $registry->toMap());
    }

    public function testTheSameLayerNameInTwoConfigsKeepsOneNameEvenWhenLayerShortDiffers(): void
    {
        $registry = $this->registry([
            'a/a' => ConfigFactory::config([ConfigFactory::layer('NS_Cities', 'Main Cities')]),
            'b/b' => ConfigFactory::config([ConfigFactory::layer('NS_Cities', 'Cities')]),
        ]);
        $this->assertSame('MainCities', $registry->get('NS_Cities'));
        $this->assertSame(['MainCities' => ['NS_Cities']], $registry->toMap());
    }

    public function testSameShortWithAnotherGeotypeGetsTheGeotypeAppended(): void
    {
        $registry = $this->registry([
            'a/a' => ConfigFactory::config(
                [ConfigFactory::layer('A_Sediments', 'Sediments', ['layer_geotype' => 'polygon'])]
            ),
            'b/b' => ConfigFactory::config(
                [ConfigFactory::layer('B_Sediments', 'Sediments', ['layer_geotype' => 'raster'])]
            ),
        ]);
        $this->assertSame('Sediments', $registry->get('A_Sediments'));
        $this->assertSame('Sediments_Raster', $registry->get('B_Sediments'));
    }

    public function testTwoLayersOfOneConfigNeverShareAGenericName(): void
    {
        $registry = $this->registry(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('A_One', 'Wind Farms'),
            ConfigFactory::layer('A_Two', 'Wind Farms'),
        ])]);
        $this->assertSame('WindFarms', $registry->get('A_One'));
        $this->assertSame('WindFarms_2', $registry->get('A_Two'));
        $this->assertCount(1, $registry->warnings());
        $this->assertStringContainsString('collision', $registry->warnings()[0]);
    }

    public function testMappingFileIsAuthoritativeAndOnlyNewLayersAreProposed(): void
    {
        $registry = GenericNameRegistry::fromMap(['Shore' => ['A_Countries', 'B_Countries']]);
        $registry->register(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('A_Countries', 'Countries'),
            ConfigFactory::layer('A_New', 'Brand New'),
        ])]);
        $this->assertSame('Shore', $registry->get('A_Countries'));
        $this->assertSame('BrandNew', $registry->get('A_New'));
        $this->assertSame(['A_New' => 'BrandNew'], $registry->proposed());
    }

    public function testLayerMappedToTwoGenericNamesIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GenericNameRegistry::fromMap(['One' => ['X'], 'Two' => ['X']]);
    }

    public function testTwoLayersOfOneConfigMappedToTheSameNameInTheFileAreRejected(): void
    {
        $registry = GenericNameRegistry::fromMap(['Shared' => ['A_One', 'A_Two']]);
        $this->expectException(\RuntimeException::class);
        $registry->register(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('A_One', 'One'),
            ConfigFactory::layer('A_Two', 'Two'),
        ])]);
    }

    public function testReferencesKeepTheirTypeSuffix(): void
    {
        $registry = $this->registry(['a/a' => ConfigFactory::config([ConfigFactory::layer('NS_Wind', 'Wind Farms')])]);
        $this->assertSame('WindFarms|3', $registry->resolveReference('NS_Wind|3'));
        $this->assertSame('WindFarms', $registry->resolveReference('NS_Wind'));
        $this->assertNull($registry->resolveReference('Unknown|1'));
    }

    public function testMapIsSortedAndRoundTripsThroughFromMap(): void
    {
        $registry = $this->registry(['a/a' => ConfigFactory::config([
            ConfigFactory::layer('Z_Layer', 'Zebra'),
            ConfigFactory::layer('A_Layer', 'Apple'),
        ])]);
        $map = $registry->toMap();
        $this->assertSame(['Apple', 'Zebra'], array_keys($map));
        $again = GenericNameRegistry::fromMap($map);
        $this->assertSame('Apple', $again->get('A_Layer'));
        $this->assertSame([], $again->proposed());
    }
}
