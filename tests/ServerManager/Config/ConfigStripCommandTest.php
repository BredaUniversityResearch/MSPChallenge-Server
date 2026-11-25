<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * app:config:strip on a temporary config root that holds the original configs plus the generic.json that
 * app:config:split would have made.
 */
class ConfigStripCommandTest extends ConfigCommandTestCase
{
    protected string $defaultFormat = 'json';

    private \stdClass $generic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeOriginals();
        $this->generic = $this->writeGeneric();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function strip(array $input = []): CommandTester
    {
        return $this->execute('app:config:strip', $input + ['--skip-validation' => true, '--parent' => 'generic']);
    }

    public function testByDefaultItOnlyReports(): void
    {
        $before = $this->snapshot();

        $tester = $this->strip();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('will_be_stripped', self::documentOf($tester)['data']['results'][0]['status']);
        $this->assertSame(
            ['will_be_stripped' => 6],
            array_count_values(array_column(self::documentOf($tester)['data']['results'], 'status'))
        );
        $this->assertFalse(self::documentOf($tester)['data']['apply']);
        $this->assertSame(0, self::documentOf($tester)['data']['written']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testCheckFailsWhileConfigsCanStillBeStripped(): void
    {
        $before = $this->snapshot();

        $tester = $this->strip(['--check' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame(['check_failed'], self::errorCodesOf($tester));
        $this->assertSame(6, self::documentOf($tester)['errors'][0]['details']['configs']);
        $this->assertSameFiles($before, $this->snapshot(), '--check never writes');
    }

    public function testApplyAndCheckCannotBeCombined(): void
    {
        $tester = $this->strip(['--apply' => true, '--check' => true]);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertContains('invalid_usage', self::errorCodesOf($tester));
    }

    public function testApplyReplacesTheConfigsByVerifiedStrippedVersions(): void
    {
        $originals = self::realConfigFiles();

        $tester = $this->strip(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(6, self::documentOf($tester)['data']['written']);
        foreach ($originals as $id => $contents) {
            $path = $this->dir . '/' . $id . '.json';
            $this->assertTrue(RegionConfigMerger::isRegionFormat($this->decode($path)), $id);
            $this->assertLessThan(strlen($contents), strlen(file_get_contents($path)), $id);
        }
        $this->assertMergesBackToTheOriginals();
        $this->assertNoTemporaryFiles();
        $this->assertFileExists($this->dir . '/generic.json', 'generic.json is not touched');
    }

    public function testAfterApplyingThereIsNothingLeftToStrip(): void
    {
        $this->strip(['--apply' => true]);
        $stripped = $this->snapshot();

        $check = $this->strip(['--check' => true]);
        $again = $this->strip(['--apply' => true]);

        $this->assertSame(0, $check->getStatusCode(), $check->getDisplay());
        $this->assertTrue(self::documentOf($check)['success']);
        $this->assertTrue(self::documentOf($check)['data']['check']);
        $this->assertSame(0, $again->getStatusCode());
        $this->assertSame(0, self::documentOf($again)['data']['written']);
        $this->assertSameFiles($stripped, $this->snapshot(), 'a second run does not touch any file');
    }

    public function testOutputDirReceivesTheResultAndTheFilesStayAsTheyAre(): void
    {
        $before = $this->snapshot();
        $output = $this->temporaryDirectory();

        $tester = $this->strip(['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSameFiles($before, $this->snapshot());
        $this->assertFileExists($output . '/generic.json', 'the parent goes along');
        $this->assertMergesBackToTheOriginals($output);
    }

    public function testTheStrippedConfigsNameTheirParent(): void
    {
        $this->strip(['--apply' => true]);

        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame('generic', $this->decode($this->dir . '/' . $id . '.json')->metadata->parent, $id);
        }
    }

    public function testAConfigCanBeStrippedAgainstAParentThatHasAParent(): void
    {
        $this->writeChildGeneric('public', 'generic');
        $output = $this->temporaryDirectory();

        $this->strip(['--parent' => 'public', '--apply' => true]);
        $this->strip(['--parent' => 'public', '--output-dir' => $output]);

        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame('public', $this->decode($this->dir . '/' . $id . '.json')->metadata->parent, $id);
        }
        $this->assertMergesBackToTheOriginals();
        $this->assertFileExists($output . '/public.json', 'the parent goes along');
        $this->assertFileExists($output . '/generic.json', 'and the parent of the parent');
        $this->assertMergesBackToTheOriginals($output);
    }

    public function testAStrippedConfigIsStrippedAgainstTheParentThatIsAskedFor(): void
    {
        $this->writeChildGeneric('public', 'generic');
        $this->strip(['--apply' => true]);

        $this->strip(['--parent' => 'public', '--apply' => true]);

        $stripped = $this->decode($this->dir . '/North_Sea_basic/North_Sea_basic_1.json');
        $this->assertSame('public', $stripped->metadata->parent);
        $this->assertMergesBackToTheOriginals();
    }

    public function testAConfigWithoutAParentNeedsToBeToldWhichOneToStripAgainst(): void
    {
        $before = $this->snapshot();

        $tester = $this->execute('app:config:strip', ['--skip-validation' => true, '--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('parent_required', self::errorCodesOf($tester));
        $this->assertStringContainsString('--parent=NAME', self::documentOf($tester)['errors'][0]['message']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testTheNameOfTheParentIsChecked(): void
    {
        $tester = $this->execute('app:config:strip', ['--skip-validation' => true, '--parent' => '../generic']);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertContains('invalid_usage', self::errorCodesOf($tester));
    }

    public function testAnyConfigFileCanBeNamedAndIsStrippedInPlace(): void
    {
        $custom = $this->temporaryDirectory() . '/My_Custom_basic_1.json';
        $config = ConfigFactory::copy(array_values(self::realConfigs())[0]);
        $config->datamodel->meta[0]->layer_tooltip = 'a tooltip of my own';
        new Filesystem()->dumpFile($custom, json_encode($config));
        $untouched = $this->snapshot();

        $tester = $this->strip(['files' => [$custom], '--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSameFiles($untouched, $this->snapshot(), 'only the named file is processed');
        $stripped = $this->decode($custom);
        $this->assertTrue(RegionConfigMerger::isRegionFormat($stripped));
        $this->assertSame(
            [],
            self::comparator()->differences(
                self::normalizer()->normalize($config),
                self::merger()->merge($this->generic, $stripped)
            )
        );
        $this->assertSame('a tooltip of my own', $stripped->datamodel->meta[0]->layer_tooltip);
    }

    public function testAConfigThatIsAlreadyStrippedIsLeftAlone(): void
    {
        $this->strip(['--apply' => true]);
        $path = $this->dir . '/North_Sea_basic/North_Sea_basic_1.json';
        $before = file_get_contents($path);

        $tester = $this->strip(['files' => [$path]]);

        $this->assertSame('already_stripped', self::documentOf($tester)['data']['results'][0]['status']);
        $this->assertSameContents($before, file_get_contents($path));
    }

    public function testAStrippedConfigIsExpandedAndStrippedAgainWhenTheGenericConfigGrows(): void
    {
        // an older generic config that does not know yet about three SEL values
        $older = ConfigFactory::copy($this->generic);
        foreach (['ship_types', 'output_configuration', 'maintenance_destinations'] as $key) {
            unset($older->datamodel->simulation_settings->SEL->{$key});
        }
        new \App\Domain\Config\ConfigDirectory($this->dir)->write($this->dir . '/generic.json', $older);
        $this->strip(['--apply' => true]);
        $sizeWithOlder = array_map('strlen', $this->snapshot());
        new \App\Domain\Config\ConfigDirectory($this->dir)->write($this->dir . '/generic.json', $this->generic);

        $tester = $this->strip(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        // Northern Mozambique has no SEL, so it has nothing to gain from the three values: already stripped
        $this->assertSame(5, self::documentOf($tester)['data']['written']);
        foreach (self::realConfigs() as $id => $original) {
            $file = $id . '.json';
            $this->assertLessThanOrEqual($sizeWithOlder[$file], strlen($this->snapshot()[$file]), $id);
        }
        $this->assertMergesBackToTheOriginals();
    }

    public function testAConfigThatCannotBeStrippedWithoutLosingMeaningIsKeptAsItIs(): void
    {
        $config = ConfigFactory::copy(array_values(self::realConfigs())[0]);
        $this->dropAGenericPortLayer($config, $this->generic);
        $path = $this->dir . '/Special/Special_basic_1.json';
        new Filesystem()->dumpFile($path, json_encode($config));
        $before = file_get_contents($path);

        $dryRun = $this->strip(['files' => [$path]]);
        $apply = $this->strip(['files' => [$path], '--apply' => true]);

        $kept = array_filter(
            self::documentOf($dryRun)['data']['results'],
            static fn(array $result) => $result['status'] === 'kept_as_is'
        );
        $this->assertCount(1, $kept);
        $this->assertStringContainsString('SEL.port_layers', implode(' ', reset($kept)['reasons']));
        $this->assertSame(0, $apply->getStatusCode(), $apply->getDisplay());
        $this->assertSameContents($before, file_get_contents($path), 'the file must stay exactly as it was');
    }

    public function testAMergedConfigThatIsEditedAndUploadedAgainIsStrippedLosslessly(): void
    {
        $id = 'North_Sea_basic/North_Sea_basic_1';
        $this->strip(['--apply' => true]);
        // what a download of the merged config gives, edited by the user and uploaded as a new file
        $merged = self::merger()->merge($this->generic, $this->decode($this->dir . '/' . $id . '.json'));
        $merged->datamodel->meta[0]->layer_tooltip = 'edited by the user';
        $upload = $this->temporaryDirectory() . '/My_Upload_basic_1.json';
        new Filesystem()->dumpFile($upload, json_encode($merged));

        $tester = $this->strip(['files' => [$upload], '--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $stripped = $this->decode($upload);
        $this->assertTrue(RegionConfigMerger::isRegionFormat($stripped));
        $this->assertLessThan(strlen(json_encode($merged)) * 0.8, strlen(json_encode($stripped)));
        $this->assertSame('edited by the user', $stripped->datamodel->meta[0]->layer_tooltip);
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize($merged),
            self::merger()->merge($this->generic, $stripped)
        ));
    }

    public function testCompleteConfigsAreValidatedAgainstTheSchemaUnlessSkipped(): void
    {
        $valid = $this->execute('app:config:strip', ['--parent' => 'generic']);
        $broken = ConfigFactory::copy(array_values(self::realConfigs())[0]);
        unset($broken->datamodel->edition_name);
        $path = $this->dir . '/Broken/Broken.json';
        new Filesystem()->dumpFile($path, json_encode($broken));
        $before = file_get_contents($path);

        $invalid = $this->execute('app:config:strip', ['files' => [$path], '--apply' => true, '--parent' => 'generic']);

        $this->assertSame(0, $valid->getStatusCode(), $valid->getDisplay());
        $this->assertSame(1, $invalid->getStatusCode());
        $this->assertSame('Broken/Broken', self::documentOf($invalid)['errors'][0]['file']);
        $message = self::documentOf($invalid)['errors'][0]['message'];
        $this->assertStringContainsString('[datamodel.edition_name]', $message);
        $this->assertSameContents($before, file_get_contents($path), 'an invalid config is not touched');
    }

    public function testItNeedsTheParent(): void
    {
        unlink($this->dir . '/generic.json');
        $before = $this->snapshot();

        $tester = $this->strip(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('parent_missing', self::errorCodesOf($tester));
        $this->assertSame('generic.json', self::documentOf($tester)['errors'][0]['details']['file']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAnInvalidFileStopsEverythingBeforeAnythingIsWritten(): void
    {
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ not json');
        $before = $this->snapshot();

        $tester = $this->strip(['--apply' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame(['file_not_json'], self::errorCodesOf($tester));
        $this->assertSame('Broken/Broken.json', self::documentOf($tester)['errors'][0]['file']);
        $this->assertSameFiles($before, $this->snapshot());
    }

    public function testAFileThatIsNoValidJsonIsSkippedWithAWarningWhenOnlyReporting(): void
    {
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ not json');

        $tester = $this->strip();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(['file_skipped'], self::warningCodesOf($tester));
    }

    public function testANamedFileThatDoesNotExistIsReported(): void
    {
        $tester = $this->strip(['files' => [$this->dir . '/nope.json']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('file_not_found', self::errorCodesOf($tester));
    }
}
