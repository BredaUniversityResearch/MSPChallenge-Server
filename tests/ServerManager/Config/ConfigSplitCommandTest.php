<?php

namespace App\Tests\ServerManager\Config;

use App\Command\ConfigSplitCommand;
use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
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
    protected string $defaultFormat = 'json';

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

    public function testTheConfigsAreValidatedAgainstTheSchemaUnlessSkipped(): void
    {
        // the original configs are old-style: they are validated in the shape of the schema
        $valid = $this->execute('app:config:split', []);

        $broken = ConfigFactory::copy(array_values(self::realConfigs())[0]);
        unset($broken->datamodel->edition_name);
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', json_encode($broken));
        $invalid = $this->execute('app:config:split', []);
        $skipped = $this->execute('app:config:split', ['--skip-validation' => true]);

        $this->assertSame(0, $valid->getStatusCode(), $valid->getDisplay());
        $this->assertSame(1, $invalid->getStatusCode());
        $this->assertContains('invalid_config', self::errorCodesOf($invalid));
        $error = self::documentOf($invalid)['errors'][0];
        $this->assertSame('Broken/Broken', $error['file']);
        $this->assertStringContainsString('[datamodel.edition_name]', $error['message']);
        $this->assertSame(0, $skipped->getStatusCode(), $skipped->getDisplay());
    }

    public function testByDefaultItOnlyReportsAndWritesNothing(): void
    {
        $before = $this->snapshot();

        $tester = $this->runSplit();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFalse(self::documentOf($tester)['data']['apply']);
        $this->assertSameFiles($before, $this->snapshot(), 'not a single file may change');
    }

    public function testApplyWritesTheGenericConfigAndStripsTheConfigsInPlace(): void
    {
        $originals = self::realConfigFiles();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertTrue(self::documentOf($tester)['data']['written']['generic']);
        $this->assertFileExists($this->dir . '/generic.json');
        $this->assertNotEmpty(get_object_vars($this->decode($this->dir . '/generic.json')->layer_names));
        foreach ($originals as $id => $contents) {
            $config = $this->decode($this->dir . '/' . $id . '.json');
            $this->assertTrue(RegionConfigMerger::isRegionFormat($config), "$id is stripped now");
            $this->assertSame('generic', $config->metadata->parent, "$id has the generic config as its parent");
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
        $this->assertMergesBackToTheOriginals($output);
    }

    public function testRunningAgainWithOutputDirNeedsForceAndThenOverwritesEverything(): void
    {
        $output = $this->temporaryDirectory();
        $this->runSplit(['--output-dir' => $output]);
        $first = $this->snapshot($output);
        // the generic config, with all its layer names, is in the config folder (as when it was copied from the
        // preview), and one name in it is edited: nothing is proposed any more, and the output has to follow it
        $this->writeGeneric();
        $edited = $this->decode($this->dir . '/generic.json');
        $edited->layer_names->MyOwnName = $edited->layer_names->Countries;
        unset($edited->layer_names->Countries);
        file_put_contents($this->dir . '/generic.json', json_encode($edited));

        $refused = $this->runSplit(['--output-dir' => $output]);
        $refusedState = $this->snapshot($output);
        $forced = $this->runSplit(['--output-dir' => $output, '--force' => true]);

        $this->assertSame(1, $refused->getStatusCode());
        $this->assertContains('generic_exists', self::errorCodesOf($refused));
        $this->assertSameFiles($first, $refusedState, 'a refused run changes nothing');
        $this->assertSame(0, $forced->getStatusCode(), $forced->getDisplay());
        $map = $this->decode($output . '/generic.json')->layer_names;
        $this->assertTrue(ConfigValues::has($map, 'MyOwnName'), 'the layer names follow the edited ones');
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
        $this->assertContains('generic_exists', self::errorCodesOf($tester));
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
        $this->assertTrue(self::documentOf($check)['success']);
        $this->assertTrue(self::documentOf($check)['data']['check']);
        $this->assertSame(6, self::documentOf($again)['data']['strippedCount']);
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
        $this->assertCount($onlyEms, self::documentOf($check)['data']['layers']['promoted']);
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
        $this->assertCount($onlyEms, self::documentOf($checkBack)['data']['layers']['demoted']);
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
        $this->assertContains('invalid_usage', self::errorCodesOf($both));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAStrippedConfigNeedsItsParent(): void
    {
        $this->runSplit(['--apply' => true]);
        unlink($this->dir . '/generic.json');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('parent_missing', self::errorCodesOf($tester));
        $this->assertSame('generic.json', self::documentOf($tester)['errors'][0]['details']['file']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testTheGenericConfigGetsTheNameThatIsAskedFor(): void
    {
        $tester = $this->runSplit(['--apply' => true, '--generic' => 'public']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists($this->dir . '/public.json');
        $this->assertFileDoesNotExist($this->dir . '/generic.json');
        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame('public', $this->decode($this->dir . '/' . $id . '.json')->metadata->parent, $id);
        }
        $this->assertMergesBackToTheOriginals();
        $this->assertSame(
            0,
            $this->runSplit(['--check' => true, '--generic' => 'public'])->getStatusCode(),
            'and splitting again changes nothing'
        );
    }

    public function testTheNameOfTheGenericConfigIsChecked(): void
    {
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true, '--generic' => '../generic']);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertContains('invalid_usage', self::errorCodesOf($tester));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAGenericConfigThatHasAParentItselfIsNotReplaced(): void
    {
        $this->writeChildGeneric('public', 'generic');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true, '--generic' => 'public', '--force' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('generic_has_parent', self::errorCodesOf($tester));
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testTheDocumentHasTheFileSizesNowAndAfter(): void
    {
        $dryRun = $this->runSplit();

        $data = self::documentOf($dryRun)['data'];
        $this->assertCount(6, $data['configs']);
        $now = 0;
        $after = 0;
        foreach ($data['configs'] as $config) {
            $this->assertSame(filesize($config['path']), $config['nowBytes'], 'the size of the file as it is now');
            $this->assertLessThan($config['nowBytes'], $config['afterBytes'], $config['id'] . ' gets smaller');
            $this->assertSame('will_be_stripped', $config['status']);
            $now += $config['nowBytes'];
            $after += $config['afterBytes'];
        }
        $this->assertFalse($data['generic']['existed']);
        $this->assertNull($data['generic']['nowBytes'], 'there is no generic config yet');
        $this->assertSame($now, $data['total']['nowBytes']);
        $this->assertSame($after + $data['generic']['afterBytes'], $data['total']['afterBytes']);
    }

    /**
     * What an upload stores: the complete final config, in the new shape, under a new version.
     */
    private function storeAnUploadOf(string $strippedId, string $newId): void
    {
        $directory = new ConfigDirectory($this->dir);
        $stripped = $directory->read($this->dir . '/' . $strippedId . '.json');
        $pool = ConfigParents::fromDirectory($directory)->poolOf($stripped, 'it');
        $directory->write($this->dir . '/' . $newId . '.json', self::merger()->merge($pool, $stripped));
    }

    public function testCopiesAndVersionsOfOneConfigAreReportedAndCompleteConfigsAreNotCalledStripped(): void
    {
        $this->runSplit(['--apply' => true]);
        $id = 'North_Sea_basic/North_Sea_basic_1';
        $this->storeAnUploadOf($id, 'North_Sea_basic/North_Sea_basic_3');
        $this->storeAnUploadOf($id, 'North_Sea_OR_ELSE_basic/North_Sea_OR_ELSE_basic_2');

        $report = $this->runSplit(['--force' => true]);

        $this->assertSame(0, $report->getStatusCode(), $report->getDisplay());
        $document = self::documentOf($report);
        $this->assertCount(8, $document['data']['configs']);
        $this->assertSame(6, $document['data']['strippedCount'], 'the uploads are complete');
        $warnings = array_column($document['warnings'], null, 'code');
        $this->assertEqualsCanonicalizing(
            [$id, 'North_Sea_basic/North_Sea_basic_3', 'North_Sea_OR_ELSE_basic/North_Sea_OR_ELSE_basic_2'],
            $warnings['same_content']['details']['configs']
        );
        $this->assertContains('several_versions', self::warningCodesOf($report));
        $larger = static fn(array $config) => $config['afterBytes'] > $config['nowBytes'];
        $this->assertNotSame(
            [],
            array_filter($document['data']['configs'], $larger),
            'what the copies share moves into generic.json: other configs get larger'
        );
        // and leaving the uploads out gives the same as before: nothing to do
        $without = self::documentOf($this->runSplit(['--pattern' => '*_1.json']));
        $this->assertSame(6, $without['data']['strippedCount']);
        $this->assertNotContains('same_content', array_column($without['warnings'], 'code'));
        $this->assertSame([], array_filter($without['data']['configs'], $larger));
    }

    public function testNothingHasChangedAfterARunThatChangesNothing(): void
    {
        $this->runSplit(['--apply' => true]);
        $modified = array_map('filemtime', glob($this->dir . '/*/*.json'));
        sleep(1);

        $generic = filemtime($this->dir . '/generic.json');

        $again = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame($modified, array_map('filemtime', glob($this->dir . '/*/*.json')), 'files are not rewritten');
        $this->assertSame($generic, filemtime($this->dir . '/generic.json'), 'the generic config is not rewritten');
        $this->assertSame(0, $again->getStatusCode(), $again->getDisplay());
        $data = self::documentOf($again)['data'];
        $this->assertTrue($data['written']['nothingToWrite']);
        $this->assertFalse($data['generic']['changed']);
        foreach ($data['configs'] as $config) {
            $this->assertSame('already_stripped', $config['status']);
            $this->assertSame($config['nowBytes'], $config['afterBytes'], 'nothing is won by splitting it again');
        }
        $this->assertSame($data['total']['nowBytes'], $data['total']['afterBytes']);
    }

    public function testWhenOnlyTheConfigsChangeTheGenericConfigIsNotRewrittenAndTheMessageSaysSo(): void
    {
        $this->runSplit(['--apply' => true]);
        $generic = filemtime($this->dir . '/generic.json');
        sleep(1);
        $this->writeOriginals(); // the complete configs are back: they have to be stripped again

        $again = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $again->getStatusCode(), $again->getDisplay());
        $this->assertSame($generic, filemtime($this->dir . '/generic.json'), 'the generic config did not change');
        $written = self::documentOf($again)['data']['written'];
        $this->assertSame(6, $written['configs']);
        $this->assertFalse($written['generic'], 'the generic config is not written');
        $this->assertFalse($written['nothingToWrite']);
        $this->assertMergesBackToTheOriginals();
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
                $options + ['--dir' => $this->dir, '--skip-validation' => true, '--format' => 'json'],
                ['decorated' => false]
            );
            return $tester;
        };

        $dryRun = $run([]);
        $applied = $run(['--apply' => true]);
        $again = $run(['--apply' => true]); // generic.json exists now: the error names it
        $report = $run(['--check' => true]); // nothing left to do now

        $this->assertSame(0, $dryRun->getStatusCode(), $dryRun->getDisplay());
        $this->assertSame(0, $applied->getStatusCode(), $applied->getDisplay());
        $this->assertSame('generic.json', self::documentOf($applied)['data']['written']['genericPath']);
        $this->assertSame(1, $again->getStatusCode());
        $this->assertSame('generic.json', self::documentOf($again)['errors'][0]['details']['path']);
        $this->assertSame(0, $report->getStatusCode(), $report->getDisplay());
        $this->assertTrue(self::documentOf($report)['data']['check']);
        $this->assertMergesBackToTheOriginals();
    }

    public function testLeftoverRegionFilesAreNotTreatedAsConfigs(): void
    {
        file_put_contents($this->dir . '/Baltic_Sea_basic/Baltic_Sea_basic_1.region.json', '{ old staging file');

        $tester = $this->runSplit();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertCount(6, self::documentOf($tester)['data']['configs']);
    }

    public function testTheLayerNamesInAnExistingGenericConfigDecideTheGenericNames(): void
    {
        // a layer name that two configs have (Baltic Sea and Western Baltic Sea share the BS_ layers)
        $layerName = 'BS_Countries';
        $configsWithIt = array_keys(array_filter(self::realConfigs(), static fn(\stdClass $config) => in_array(
            $layerName,
            array_map(static fn($layer) => $layer->layer_name, $config->datamodel->meta),
            true
        )));
        $this->assertGreaterThan(1, count($configsWithIt));
        file_put_contents($this->dir . '/generic.json', json_encode([
            'metadata' => ['config_version' => '2.0.0'],
            'datamodel' => ['meta' => []],
            'layer_names' => ['MyOwnName' => [$layerName]],
        ]));

        $tester = $this->runSplit(['--apply' => true, '--force' => true]);

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
        $this->assertContains($layerName, $this->decode($this->dir . '/generic.json')->layer_names->MyOwnName);
    }

    public function testReportsTheSimulationSections(): void
    {
        $sections = array_column(self::documentOf($this->runSplit())['data']['sections'], 'name');

        $this->assertContains('CEL', $sections);
        $this->assertContains('SEL.ship_types', $sections);
        $this->assertContains('restrictions', $sections);
    }

    public function testStopsOnAFileThatIsNoValidJsonWhenWriting(): void
    {
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ not json');
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame(['file_not_json'], self::errorCodesOf($tester));
        $this->assertSame('Broken/Broken.json', self::documentOf($tester)['errors'][0]['file']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAFileThatIsNoValidJsonIsSkippedWithAWarningInTheReport(): void
    {
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ not json');

        $tester = $this->runSplit();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertContains('file_skipped', self::warningCodesOf($tester));
        $skipped = array_column(self::documentOf($tester)['warnings'], null, 'code')['file_skipped'];
        $this->assertSame('Broken/Broken.json', $skipped['details']['path']);
        $this->assertCount(6, self::documentOf($tester)['data']['configs']);
    }

    public function testAnInvalidGenericConfigCanBeOverwrittenAndIsNotReportedAsSkipped(): void
    {
        new Filesystem()->dumpFile($this->dir . '/generic.json', '{ not json');

        $tester = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertContains('generic_unusable', self::warningCodesOf($tester));
        $this->assertNotContains('file_skipped', self::warningCodesOf($tester));
        $this->assertMergesBackToTheOriginals();
    }

    public function testFailsWhenTheDirectoryDoesNotExist(): void
    {
        $tester = $this->runSplit(['--dir' => $this->dir . '/does-not-exist']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('dir_not_found', self::errorCodesOf($tester));
    }
}
