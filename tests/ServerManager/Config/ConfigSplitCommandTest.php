<?php

namespace App\Tests\ServerManager\Config;

use App\Command\ConfigSplitCommand;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Config\Split\ConfigValues;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs app:config:split on a temporary copy of the original configs. Input validation is skipped: it needs the
 * Swaggest schema validator and is exercised by the real command run.
 */
class ConfigSplitCommandTest extends ConfigCommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeOriginals();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runSplit(array $options = []): \Symfony\Component\Console\Tester\CommandTester
    {
        return $this->execute('app:config:split', $options + ['--skip-validation' => true]);
    }

    public function testByDefaultItOnlyReportsAndWritesNothing(): void
    {
        $before = $this->snapshot();

        $tester = $this->runSplit();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Dry run, nothing written', self::text($tester));
        $this->assertSameFiles($before, $this->snapshot(), 'not a single file may change');
    }

    public function testApplyWritesTheGenericConfigAndTheNameMapAndStripsTheConfigsInPlace(): void
    {
        $originals = self::realConfigFiles();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('[OK] Wrote', self::text($tester));
        $this->assertFileExists($this->dir . '/generic.json');
        $this->assertNotEmpty(get_object_vars($this->decode($this->dir . '/layer_generic_names.json')->layers));
        foreach ($originals as $id => $contents) {
            $config = $this->decode($this->dir . '/' . $id . '.json');
            $this->assertTrue(RegionConfigMerger::isRegionFormat($config), "$id is stripped now");
            $this->assertLessThan(strlen($contents), strlen(file_get_contents($this->dir . '/' . $id . '.json')), $id);
            $this->assertFileDoesNotExist($this->dir . '/' . $id . '.region.json', 'no staging files any more');
        }
        $this->assertMergesBackToTheOriginals();
        $this->assertNoTemporaryFiles();
    }

    public function testOutputDirWritesEverythingElsewhereAndLeavesTheConfigsAlone(): void
    {
        $before = $this->snapshot();
        $output = $this->temporaryDirectory();

        $tester = $this->runSplit(['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSameFiles($before, $this->snapshot(), 'the config root is not touched');
        $this->assertFileExists($output . '/generic.json');
        $this->assertFileExists($output . '/layer_generic_names.json');
        $this->assertMergesBackToTheOriginals($output);
    }

    public function testRunningAgainWithOutputDirNeedsForceAndThenOverwritesEverything(): void
    {
        $output = $this->temporaryDirectory();
        $this->runSplit(['--output-dir' => $output]);
        $first = $this->snapshot($output);
        // the complete name map is in the config folder (as when it was copied from the preview), and one name in it
        // is edited: nothing is proposed any more, and the output still has to follow the map
        $this->writeGeneric();
        $edited = $this->decode($this->dir . '/layer_generic_names.json');
        $edited->layers->MyOwnName = $edited->layers->Countries;
        unset($edited->layers->Countries);
        file_put_contents($this->dir . '/layer_generic_names.json', json_encode($edited));

        $refused = $this->runSplit(['--output-dir' => $output]);
        $refusedState = $this->snapshot($output);
        $forced = $this->runSplit(['--output-dir' => $output, '--force' => true]);

        $this->assertSame(1, $refused->getStatusCode());
        $this->assertStringContainsString('use --force to overwrite it', self::text($refused));
        $this->assertSameFiles($first, $refusedState, 'a refused run changes nothing');
        $this->assertSame(0, $forced->getStatusCode(), $forced->getDisplay());
        $map = $this->decode($output . '/layer_generic_names.json')->layers;
        $this->assertTrue(ConfigValues::has($map, 'MyOwnName'), 'the name map follows the edited one');
        $names = array_map(
            static fn($layer) => $layer->msp_config_generic_name,
            $this->decode($output . '/generic.json')->datamodel->meta
        );
        $this->assertContains('MyOwnName', $names);
        $this->assertMergesBackToTheOriginals($output);
    }

    public function testTwoRunsGiveTheSameFiles(): void
    {
        $first = $this->temporaryDirectory();
        $second = $this->temporaryDirectory();

        $this->runSplit(['--output-dir' => $first]);
        $this->runSplit(['--output-dir' => $second]);

        $this->assertSameFiles($this->snapshot($first), $this->snapshot($second));
    }

    public function testItDoesNotOverwriteAGenericConfigWithoutForce(): void
    {
        file_put_contents($this->dir . '/generic.json', 'HAND EDITED');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('use --force to overwrite it', self::text($tester));
        $this->assertSameFiles($before, $this->snapshot(), 'nothing may be written, the configs stay complete');
    }

    public function testForceOverwritesTheGenericConfig(): void
    {
        file_put_contents($this->dir . '/generic.json', 'HAND EDITED');

        $tester = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertNotEmpty($this->decode($this->dir . '/generic.json')->datamodel->meta);
        $this->assertMergesBackToTheOriginals();
    }

    public function testStrippedConfigsAreExpandedAndSplitAgainWithoutChangingAnything(): void
    {
        $this->runSplit(['--apply' => true]);
        $before = $this->snapshot();

        $check = $this->runSplit(['--check' => true]);
        $again = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $check->getStatusCode(), $check->getDisplay());
        $this->assertStringContainsString('Nothing to re-split.', self::text($check));
        $this->assertStringContainsString('6 of them are stripped already', self::text($again));
        $this->assertSame(0, $again->getStatusCode(), $again->getDisplay());
        $this->assertSameFiles($before, $this->snapshot(), 're-splitting stripped configs is a fixed point');
    }

    /**
     * @return array{0: int, 1: string} the number of layers in generic.json, and its contents
     */
    private function genericLayers(): array
    {
        return [
            count($this->decode($this->dir . '/generic.json')->datamodel->meta),
            file_get_contents($this->dir . '/generic.json'),
        ];
    }

    private function layersWithoutGenericName(string $id): int
    {
        return count(array_filter(
            $this->decode($this->dir . '/' . $id . '.json')->datamodel->meta,
            static fn(\stdClass $layer) => !isset($layer->msp_config_generic_name)
        ));
    }

    public function testLayersThatASecondConfigUsesBecomeGenericAndMoveBackWhenItIsGone(): void
    {
        $ems = 'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1';
        $this->runSplit(['--apply' => true]);
        [$layersBefore, $genericBefore] = $this->genericLayers();
        $onlyEms = $this->layersWithoutGenericName($ems);
        $this->assertGreaterThan(0, $onlyEms);

        // a new config that has the same layers as Eastern Mediterranean Sea
        new Filesystem()->dumpFile(
            $this->dir . '/Copy_of_EMS_basic/Copy_of_EMS_basic_1.json',
            self::realConfigFiles()[$ems]
        );
        $check = $this->runSplit(['--check' => true]);
        $promote = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(1, $check->getStatusCode(), 'a re-split would change things');
        $this->assertStringContainsString("$onlyEms layer(s) become generic", self::text($check));
        $this->assertSame(0, $promote->getStatusCode(), $promote->getDisplay());
        $this->assertSame($layersBefore + $onlyEms, $this->genericLayers()[0]);
        $this->assertSame(0, $this->layersWithoutGenericName($ems), 'they are inherited now');
        $this->assertSame(0, $this->layersWithoutGenericName('Copy_of_EMS_basic/Copy_of_EMS_basic_1'));
        $this->assertMergesBackToTheOriginals();
        $this->assertSame([], self::differencesToOriginal(
            $this->decode($this->dir . '/generic.json'),
            $this->decode($this->dir . '/Copy_of_EMS_basic/Copy_of_EMS_basic_1.json'),
            self::realConfigs()[$ems]
        ));
        $this->assertSame(0, $this->runSplit(['--check' => true])->getStatusCode(), 'and that is stable');

        // the new config is removed again: the layers only one config uses move back out of generic.json
        unlink($this->dir . '/Copy_of_EMS_basic/Copy_of_EMS_basic_1.json');
        $checkBack = $this->runSplit(['--check' => true]);
        $demote = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(1, $checkBack->getStatusCode());
        $this->assertStringContainsString("$onlyEms layer(s) move back", self::text($checkBack));
        $this->assertSame(0, $demote->getStatusCode(), $demote->getDisplay());
        $this->assertSameContents($genericBefore, $this->genericLayers()[1], 'generic.json is what it was');
        $this->assertSame($onlyEms, $this->layersWithoutGenericName($ems));
        $this->assertMergesBackToTheOriginals();
    }

    public function testCheckNeverWritesAndCannotBeCombinedWithApply(): void
    {
        $before = $this->snapshot();

        $fresh = $this->runSplit(['--check' => true]);
        $both = $this->runSplit(['--check' => true, '--apply' => true]);

        $this->assertSame(1, $fresh->getStatusCode(), 'there is no generic.json yet, so there is work to do');
        $this->assertSame(2, $both->getStatusCode());
        $this->assertStringContainsString('Use --check on its own', self::text($both));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAStrippedConfigNeedsTheGenericConfigItWasStrippedAgainst(): void
    {
        $this->runSplit(['--apply' => true]);
        unlink($this->dir . '/generic.json');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('no usable generic.json to expand it with', self::text($tester));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testNothingHasChangedAfterARunThatChangesNothing(): void
    {
        $this->runSplit(['--apply' => true]);
        $modified = array_map('filemtime', glob($this->dir . '/*/*.json'));
        sleep(1);

        $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame($modified, array_map('filemtime', glob($this->dir . '/*/*.json')), 'files are not rewritten');
    }

    public function testAConfigFolderOnAnotherDriveThanTheProjectDoesNotBreakTheMessages(): void
    {
        // On Windows the project can be on drive D: while the temporary directory is on C:. Path::makeRelative()
        // throws for paths with different roots, which stopped the command when it printed where it wrote. A project
        // directory in "vfs://" stream wrapper style has another root than the config folder on every OS.
        $command = new ConfigSplitCommand('vfs://msp-project', new SessionConfigValidator('/not/used'));
        $run = function (array $options) use ($command): CommandTester {
            $tester = new CommandTester($command);
            $tester->execute(
                $options + ['--dir' => $this->dir, '--skip-validation' => true],
                ['decorated' => false]
            );
            return $tester;
        };

        $dryRun = $run([]);
        $applied = $run(['--apply' => true]);
        $again = $run(['--apply' => true]); // generic.json exists now: the error names it
        $report = $run(['--check' => true]); // the report names the mapping file

        $this->assertSame(0, $dryRun->getStatusCode(), $dryRun->getDisplay());
        $this->assertSame(0, $applied->getStatusCode(), $applied->getDisplay());
        $this->assertStringContainsString('Wrote ' . $this->dir . '/generic.json', self::text($applied));
        $this->assertSame(1, $again->getStatusCode());
        $this->assertStringContainsString($this->dir . '/generic.json exists', self::text($again));
        $this->assertSame(0, $report->getStatusCode(), $report->getDisplay());
        $this->assertStringContainsString('mapping file: layer_generic_names.json', self::text($report));
        $this->assertMergesBackToTheOriginals();
    }

    public function testLeftoverRegionFilesAreNotTreatedAsConfigs(): void
    {
        file_put_contents($this->dir . '/Baltic_Sea_basic/Baltic_Sea_basic_1.region.json', '{ old staging file');

        $tester = $this->runSplit();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Splitting 6 configs', self::text($tester));
    }

    public function testAnExistingNameMapDecidesTheGenericNames(): void
    {
        // a layer name that two configs have (Baltic Sea and Western Baltic Sea share the BS_ layers)
        $layerName = 'BS_Countries';
        $configsWithIt = array_keys(array_filter(self::realConfigs(), static fn(\stdClass $config) => in_array(
            $layerName,
            array_map(static fn($layer) => $layer->layer_name, $config->datamodel->meta),
            true
        )));
        $this->assertGreaterThan(1, count($configsWithIt));
        file_put_contents(
            $this->dir . '/layer_generic_names.json',
            json_encode(['layers' => ['MyOwnName' => [$layerName]]])
        );

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $names = array_map(
            static fn($l) => $l->msp_config_generic_name,
            $this->decode($this->dir . '/generic.json')->datamodel->meta
        );
        $this->assertContains('MyOwnName', $names);
        foreach ($configsWithIt as $id) {
            $entries = array_filter(
                $this->decode($this->dir . '/' . $id . '.json')->datamodel->meta,
                static fn($layer) => ($layer->layer_name ?? null) === $layerName
            );
            $this->assertSame('MyOwnName', reset($entries)->msp_config_generic_name, $id);
        }
        $this->assertContains($layerName, $this->decode($this->dir . '/layer_generic_names.json')->layers->MyOwnName);
    }

    public function testSizesAreReportedHumanReadable(): void
    {
        $display = self::text($this->runSplit());

        $this->assertMatchesRegularExpression('/\b\d+(\.\d+)? kb\b/', $display);
        $this->assertStringContainsString('generic.json:', $display);
    }

    public function testReportsTheSimulationSections(): void
    {
        $display = self::text($this->runSplit());

        $this->assertStringContainsString('CEL', $display);
        $this->assertStringContainsString('SEL.ship_types', $display);
        $this->assertStringContainsString('restrictions', $display);
    }

    public function testStopsOnAnInvalidConfigFile(): void
    {
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ not json');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('these configs are invalid', self::text($tester));
        $this->assertStringContainsString('Broken/Broken', self::text($tester));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testFailsWhenTheDirectoryDoesNotExist(): void
    {
        $tester = $this->runSplit(['--dir' => $this->dir . '/does-not-exist']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Config directory not found', self::text($tester));
    }
}
