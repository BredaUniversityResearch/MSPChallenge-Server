<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\Merge\ConfigFileStripper;
use App\Domain\Config\Merge\StripPlan;

/**
 * The configs that are shipped in ServerManager/configfiles, in whatever state they are (complete, or stripped):
 * merged with their parents they must still give the original configs of ConfigFixtures. This is what keeps the
 * generic configs and the stripped configs consistent with each other in the repository.
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

    private static function parents(): ConfigParents
    {
        return ConfigParents::fromDirectory(new ConfigDirectory(self::configDir()));
    }

    public function testEveryShippedConfigStillGivesItsOriginalWhenMergedWithItsParents(): void
    {
        $directory = new ConfigDirectory(self::configDir());

        foreach (self::shipped() as $id => $path) {
            $config = $directory->read($path);
            $this->assertSame(
                [],
                self::comparator()->differences(
                    self::normalizer()->normalize(self::realConfigs()[$id]),
                    self::normalizer()->normalize(self::merger()->merge(self::parents()->poolOf($config, $id), $config))
                ),
                "$id no longer gives its original config: run app:config:verify to see why"
            );
        }
    }

    public function testStrippedShippedConfigsCannotBeStrippedFurther(): void
    {
        $directory = new ConfigDirectory(self::configDir());
        $parents = self::parents();
        $stripper = new ConfigFileStripper();

        $checked = 0;
        foreach (self::shipped() as $id => $path) {
            $config = $directory->read($path);
            $parent = ConfigParents::parentOf($config);
            if ($parent === null) {
                continue;
            }
            $plan = $stripper->plan(
                $id,
                $path,
                $config,
                $parents->poolOfParent($parent, $id),
                $parents->namesOfParent($parent, $id),
                self::merger()->merge($parents->poolOf($config, $id), $config),
                $parent
            );
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

    public function testTheGenericConfigsOfTheShippedConfigsKnowTheirLayerNames(): void
    {
        $directory = new ConfigDirectory(self::configDir());
        $parents = self::parents();

        $checked = 0;
        foreach (self::shipped() as $id => $path) {
            $parent = ConfigParents::parentOf($directory->read($path));
            if ($parent === null) {
                continue;
            }
            $map = $parents->nameMapOfParent($parent, $id);
            $names = $parents->namesOfParent($parent, $id);
            // every layer has a generic name, also the ones that only one config uses: they are not in the generic
            // config (it holds the layers 2 or more configs use), but their name is kept so they can join it later
            foreach (self::realConfigs()[$id]->datamodel->meta as $layer) {
                $this->assertNotNull($names->get($layer->layer_name), "$id: no generic name for $layer->layer_name");
            }
            // and every layer of the generic configs is in the layer names, with the layer names that use it
            foreach ($parents->poolOfParent($parent, $id)->datamodel->meta as $layer) {
                $this->assertNotEmpty($map[$layer->msp_config_generic_name] ?? [], $layer->msp_config_generic_name);
            }
            $checked++;
        }
        if ($checked === 0) {
            $this->markTestSkipped('The shipped configs are not stripped yet.');
        }
    }
}
