<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Split\ConfigValues;
use Symfony\Component\Filesystem\Filesystem;

class ConfigMergeCommandTest extends ConfigCommandTestCase
{
    private \stdClass $generic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeOriginals();
        $this->generic = $this->writeGeneric();
    }

    private function strippedCopyOf(string $id): string
    {
        return $this->strippedCopy() . '/' . $id . '.json';
    }

    public function testItPrintsTheFinalConfigOfAStrippedFile(): void
    {
        $id = 'North_Sea_basic/North_Sea_basic_1';
        $stripped = $this->strippedCopyOf($id);

        $tester = $this->execute('app:config:merge', ['file' => $stripped]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $merged = json_decode($tester->getDisplay(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            [],
            self::comparator()->differences(
                self::normalizer()->normalize(self::realConfigs()[$id]),
                $merged
            ),
            'the printed config is the original one (in the new shape)'
        );
        $this->assertTrue(ConfigValues::has($merged->datamodel, 'simulation_settings'));
        $this->assertFalse(ConfigValues::has($merged->datamodel, 'SEL'));
        $this->assertFalse(ConfigValues::has($merged->metadata, 'parent'), 'the final config has no parent');
    }

    public function testItFollowsAChainOfParents(): void
    {
        $id = 'North_Sea_basic/North_Sea_basic_1';
        $this->writeChildGeneric('public', 'generic');
        $output = $this->temporaryDirectory();
        $this->execute('app:config:strip', [
            '--output-dir' => $output,
            '--parent' => 'public',
            '--skip-validation' => true,
        ]);

        $tester = $this->execute('app:config:merge', ['file' => $output . '/' . $id . '.json', '--dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('public', $this->decode($output . '/' . $id . '.json')->metadata->parent);
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[$id]),
            json_decode($tester->getDisplay(), false, 512, JSON_THROW_ON_ERROR)
        ));
    }

    public function testACompleteConfigIsPrintedInTheNewShape(): void
    {
        $id = 'Baltic_Sea_basic/Baltic_Sea_basic_1';

        $tester = $this->execute('app:config:merge', ['file' => $this->dir . '/' . $id . '.json']);

        $merged = json_decode($tester->getDisplay(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertSameJson(
            RegionConfigMerger::toSimulationSettings(self::realConfigs()[$id]),
            $merged,
            'nothing but the simulation settings is moved'
        );
    }

    public function testTheOutputIsRawJsonEvenWhenItLooksLikeConsoleMarkup(): void
    {
        $config = ConfigFactory::copy(self::realConfigs()['Baltic_Sea_basic/Baltic_Sea_basic_1']);
        $config->datamodel->meta[0]->layer_tooltip = '<info>not markup</info> <b>and not html</b>';
        $path = $this->temporaryDirectory() . '/markup.json';
        new Filesystem()->dumpFile($path, json_encode($config));

        $tester = $this->execute('app:config:merge', ['file' => $path]);

        $merged = json_decode($tester->getDisplay(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('<info>not markup</info> <b>and not html</b>', $merged->datamodel->meta[0]->layer_tooltip);
    }

    public function testOldStyleKeysInAFileAreAppliedAndNotPrinted(): void
    {
        $stripped = $this->strippedCopyOf('North_Sea_basic/North_Sea_basic_1');
        $config = $this->decode($stripped);
        $config->datamodel->MEL = ConfigFactory::json('{"rows": 999}');
        file_put_contents($stripped, json_encode($config));

        $tester = $this->execute('app:config:merge', ['file' => $stripped]);

        $merged = json_decode($tester->getDisplay(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(999, $merged->datamodel->simulation_settings->MEL->rows);
        $this->assertFalse(ConfigValues::has($merged->datamodel, 'MEL'), 'old-style keys are not in the result');
    }

    public function testItCanWriteTheResultToAFile(): void
    {
        $id = 'Baltic_Sea_basic/Baltic_Sea_basic_1';
        $target = $this->temporaryDirectory() . '/merged/final.json';

        $tester = $this->execute('app:config:merge', [
            'file' => $this->strippedCopyOf($id),
            '--output' => $target,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Wrote ' . $target, self::text($tester));
        $this->assertSame(
            [],
            self::comparator()->differences(
                self::normalizer()->normalize(self::realConfigs()[$id]),
                $this->decode($target)
            )
        );
    }

    public function testAStrippedConfigNeedsItsParent(): void
    {
        $stripped = $this->strippedCopyOf('Baltic_Sea_basic/Baltic_Sea_basic_1');
        unlink($this->dir . '/generic.json');

        $tester = $this->execute('app:config:merge', ['file' => $stripped]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('The parent "generic" of', self::text($tester));
        $this->assertStringContainsString('generic.json is needed', self::text($tester));
    }

    public function testACompleteConfigNeedsNoParent(): void
    {
        unlink($this->dir . '/generic.json');

        $tester = $this->execute(
            'app:config:merge',
            ['file' => $this->dir . '/Baltic_Sea_basic/Baltic_Sea_basic_1.json']
        );

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testAStrippedConfigThatDoesNotNameItsParentIsRefused(): void
    {
        $path = $this->strippedCopyOf('Baltic_Sea_basic/Baltic_Sea_basic_1');
        $config = $this->decode($path);
        unset($config->metadata->parent);
        file_put_contents($path, json_encode($config));

        $tester = $this->execute('app:config:merge', ['file' => $path]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('but no metadata.parent', self::text($tester));
    }

    public function testAParentThatIsNoGenericConfigIsRefused(): void
    {
        $stripped = $this->strippedCopyOf('Baltic_Sea_basic/Baltic_Sea_basic_1');
        // a complete config as the parent: its layers do not have a generic name
        copy($this->dir . '/North_Sea_basic/North_Sea_basic_1.json', $this->dir . '/generic.json');

        $tester = $this->execute('app:config:merge', ['file' => $stripped]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('cannot be a parent', self::text($tester));
    }

    public function testAMissingFileIsReported(): void
    {
        $tester = $this->execute('app:config:merge', ['file' => $this->dir . '/nope.json']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', self::text($tester));
    }
}
