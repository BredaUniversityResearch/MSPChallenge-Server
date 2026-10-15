<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Merge\ConfigFileStripper;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Merge\StripPlan;
use App\Domain\Config\Split\ConfigValues;

class ConfigFileStripperTest extends ConfigCommandTestCase
{
    private const string ID = 'North_Sea_basic/North_Sea_basic_1';

    private ConfigFileStripper $stripper;
    private ConfigDirectory $directory;
    private \stdClass $generic;
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeOriginals();
        $this->generic = $this->writeGeneric();
        $this->stripper = new ConfigFileStripper();
        $this->directory = new ConfigDirectory($this->dir);
        $this->path = $this->dir . '/' . self::ID . '.json';
    }

    private function plan(?\stdClass $current = null): StripPlan
    {
        [, $registry] = self::realSplit();
        return $this->stripper->plan(
            self::ID,
            $this->path,
            $current ?? $this->directory->read($this->path),
            $this->generic,
            $registry
        );
    }

    public function testACompleteConfigHasAStrippedVersionToWrite(): void
    {
        $plan = $this->plan();

        $this->assertSame(StripPlan::CHANGED, $plan->status);
        $this->assertTrue(RegionConfigMerger::isRegionFormat($plan->stripped));
        $this->assertLessThan(self::byteSize($plan->current), self::byteSize($plan->stripped));
        $this->assertSame(self::ID, $plan->id);
        $this->assertSame($this->path, $plan->path);
        $this->assertSame([], $plan->reasons);
        $this->assertGreaterThan(0, $plan->stats['layers inherited']);
        $this->assertSame(
            count($plan->current->datamodel->meta),
            $plan->stats['layers inherited'] + $plan->stats['layers standalone']
        );
    }

    public function testApplyReplacesTheFileAndTheResultMergesBack(): void
    {
        $plan = $this->plan();

        $problems = $this->stripper->apply($plan, $this->directory, $this->generic);

        $this->assertSame([], $problems);
        $this->assertSameContents(ConfigDirectory::encode($plan->stripped), file_get_contents($this->path));
        $this->assertSame([], self::differencesToOriginal(
            $this->generic,
            $this->decode($this->path),
            self::realConfigs()[self::ID]
        ));
        $this->assertNoTemporaryFiles();
    }

    public function testAStrippedConfigIsUnchangedTheNextTime(): void
    {
        $this->stripper->apply($this->plan(), $this->directory, $this->generic);

        $again = $this->plan();

        $this->assertSame(StripPlan::UNCHANGED, $again->status);
        $this->assertNotEmpty($this->stripper->apply($again, $this->directory, $this->generic), 'nothing to write');
    }

    public function testAnUnchangedPlanIsOnlyWrittenElsewhereWhenAskedTo(): void
    {
        $this->stripper->apply($this->plan(), $this->directory, $this->generic);
        $again = $this->plan();
        $target = $this->temporaryDirectory() . '/copy.json';

        $this->assertNotEmpty($this->stripper->apply($again, $this->directory, $this->generic, $target));
        $this->assertFileDoesNotExist($target);
        $this->assertSame([], $this->stripper->apply($again, $this->directory, $this->generic, $target, true));
        // same config (a stripped file is re-derived from its merged form, so key order may differ)
        $this->assertSameJson($this->decode($this->path), $this->decode($target));
    }

    public function testApplyDoesNotReplaceTheFileWhenTheStrippedVersionDoesNotGiveTheOriginal(): void
    {
        $plan = $this->plan();
        $corrupted = ConfigFactory::copy($plan->stripped);
        $corrupted->datamodel->meta[0]->layer_tooltip = 'this is not in the original';
        $before = file_get_contents($this->path);

        $problems = $this->stripper->apply(
            new StripPlan($plan->id, $plan->path, StripPlan::CHANGED, $plan->current, $corrupted),
            $this->directory,
            $this->generic
        );

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('layer_tooltip differs', implode("\n", $problems));
        $this->assertSameContents($before, file_get_contents($this->path), 'the file stays exactly as it was');
        $this->assertNoTemporaryFiles();
    }

    public function testApplyDoesNotReplaceTheFileWhenTheStrippedVersionLosesSomething(): void
    {
        $plan = $this->plan();
        $corrupted = ConfigFactory::copy($plan->stripped);
        array_pop($corrupted->datamodel->meta);
        $before = file_get_contents($this->path);

        $problems = $this->stripper->apply(
            new StripPlan($plan->id, $plan->path, StripPlan::CHANGED, $plan->current, $corrupted),
            $this->directory,
            $this->generic
        );

        $this->assertNotEmpty($problems);
        $this->assertSameContents($before, file_get_contents($this->path));
    }

    public function testTheConfigAFileHasToKeepGivingBackIsTheExpandedOneWhenOneIsGiven(): void
    {
        $effective = self::merger()->merge($this->generic, $this->directory->read($this->path));
        $effective->datamodel->meta[0]->layer_tooltip = 'what the stripped file has to give back';
        [, $registry] = self::realSplit();

        $plan = $this->stripper->plan(
            self::ID,
            $this->path,
            $this->directory->read($this->path),
            $this->generic,
            $registry,
            $effective
        );
        $problems = $this->stripper->apply($plan, $this->directory, $this->generic);

        $this->assertSame($effective, $plan->effective);
        $this->assertSame([], $problems);
        $this->assertSame(
            'what the stripped file has to give back',
            self::merger()->merge($this->generic, $this->decode($this->path))->datamodel->meta[0]->layer_tooltip
        );
    }

    public function testStagedFilesAreOnlyPutInPlaceWhenTheyAreCommitted(): void
    {
        $plan = $this->plan();
        $before = file_get_contents($this->path);

        [$problems, $temporary] = $this->stripper->stage($plan, $this->directory, $this->generic);

        $this->assertSame([], $problems);
        $this->assertFileExists($temporary);
        $this->assertSameContents($before, file_get_contents($this->path), 'not replaced yet');
        $this->directory->commit($temporary, $this->path);
        $this->assertFileDoesNotExist($temporary);
        $this->assertSameContents(ConfigDirectory::encode($plan->stripped), file_get_contents($this->path));
    }

    public function testAConfigThatWouldChangeMeaningIsRefusedAndNeverWritten(): void
    {
        $config = ConfigFactory::copy(self::realConfigs()[self::ID]);
        $this->dropAGenericPortLayer($config, $this->generic);

        $plan = $this->plan($config);

        $this->assertSame(StripPlan::REFUSED, $plan->status);
        $this->assertNull($plan->stripped);
        $this->assertStringContainsString('SEL.port_layers', $plan->reasons[0]);
        $before = file_get_contents($this->path);
        $this->assertNotEmpty($this->stripper->apply($plan, $this->directory, $this->generic));
        $this->assertSameContents($before, file_get_contents($this->path));
    }

    public function testTheVerifiedResultIsWhatAMergeCommandWouldPrint(): void
    {
        $plan = $this->plan();
        $this->stripper->apply($plan, $this->directory, $this->generic);

        $merged = self::merger()->merge($this->generic, $this->decode($this->path));

        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::ID]),
            $merged
        ));
        $this->assertTrue(ConfigValues::has($merged->datamodel, 'simulation_settings'));
        $this->assertFalse(ConfigValues::has($merged->datamodel, 'SEL'));
    }
}
