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
        $outputDirectory = $this->temporaryDirectory();
        $this->execute('app:config:strip', ['--output-dir' => $outputDirectory, '--skip-validation' => true]);
        return $outputDirectory . '/' . $id . '.json';
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

    public function testItNeedsTheGenericConfig(): void
    {
        unlink($this->dir . '/generic.json');

        $tester = $this->execute(
            'app:config:merge',
            ['file' => $this->dir . '/Baltic_Sea_basic/Baltic_Sea_basic_1.json']
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('generic.json', self::text($tester));
    }

    public function testAMissingFileIsReported(): void
    {
        $tester = $this->execute('app:config:merge', ['file' => $this->dir . '/nope.json']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', self::text($tester));
    }
}
