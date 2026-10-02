<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Merge\ConfigFileStripper;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Merge\StripPlan;

/**
 * The configs that are shipped in ServerManager/configfiles, in whatever state they are (complete, or stripped):
 * merged with generic.json they must still give the original configs of ConfigFixtures. This is what keeps
 * generic.json, the name map and the stripped configs consistent with each other in the repository.
 */
class ShippedConfigsTest extends ConfigTestCase
{
    /**
     * @return array<string, string> config id => path, of the original configs that are shipped
     */
    private static function shipped(): array
    {
        $directory = new ConfigDirectory(self::configDir());
        $shipped = array_intersect_key($directory->configFiles(), self::realConfigs());
        if ($shipped === []) {
            self::markTestSkipped('None of the original configs is shipped in ' . self::configDir());
        }
        return $shipped;
    }

    /**
     * @throws \JsonException
     */
    private static function generic(): \stdClass
    {
        $directory = new ConfigDirectory(self::configDir());
        return $directory->hasGeneric()
            ? $directory->loadGeneric()
            : ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');
    }

    public function testEveryShippedConfigStillGivesItsOriginalWhenMergedWithTheGenericConfig(): void
    {
        $generic = self::generic();
        $directory = new ConfigDirectory(self::configDir());

        foreach (self::shipped() as $id => $path) {
            $this->assertSame(
                [],
                self::comparator()->differences(
                    self::normalizer()->normalize(self::realConfigs()[$id]),
                    self::normalizer()->normalize(self::merger()->merge($generic, $directory->read($path)))
                ),
                "$id no longer gives its original config: run app:config:verify to see why"
            );
        }
    }

    public function testStrippedShippedConfigsCannotBeStrippedFurther(): void
    {
        $directory = new ConfigDirectory(self::configDir());
        if (!$directory->hasGeneric()) {
            $this->markTestSkipped('No generic.json shipped yet.');
        }
        $generic = $directory->loadGeneric();
        $names = $directory->loadNames();
        $stripper = new ConfigFileStripper();

        $checked = 0;
        foreach (self::shipped() as $id => $path) {
            $config = $directory->read($path);
            if (!RegionConfigMerger::isRegionFormat($config)) {
                continue;
            }
            $plan = $stripper->plan($id, $path, $config, $generic, $names);
            $this->assertSame(
                StripPlan::UNCHANGED,
                $plan->status,
                "$id can be stripped further (or not at all): run app:config:strip --apply"
            );
            $checked++;
        }
        if ($checked === 0) {
            $this->markTestSkipped('The shipped configs are not stripped yet.');
        }
    }

    public function testTheShippedNameMapKnowsEveryLayerAndEveryGenericLayer(): void
    {
        $directory = new ConfigDirectory(self::configDir());
        if (!$directory->hasGeneric() || !is_file($directory->nameMapPath())) {
            $this->markTestSkipped('No generic.json and name map shipped yet.');
        }
        $names = $directory->loadNames();
        $map = $directory->loadNameMap();

        // every layer has a generic name, also the ones that only one config uses: they are not in generic.json (it
        // holds the layers 2 or more configs use), but the map keeps their name, so they can join it later
        foreach (self::realConfigs() as $id => $config) {
            foreach ($config->datamodel->meta as $layer) {
                $this->assertNotNull($names->get($layer->layer_name), "$id: no generic name for $layer->layer_name");
            }
        }
        // and every layer of generic.json is in the map, with the layer names that use it
        foreach ($directory->loadGeneric()->datamodel->meta as $layer) {
            $this->assertNotEmpty($map[$layer->msp_config_generic_name] ?? [], $layer->msp_config_generic_name);
        }
    }
}
