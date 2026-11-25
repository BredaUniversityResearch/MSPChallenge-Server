<?php

namespace App\Tests\ServerManager\Config;

/**
 * app:config:merge-all: the final config of every config below a folder, written to another folder. For an editor or a
 * tool that needs complete configs, and a way to see what the server makes of a folder of stripped configs.
 */
class ConfigMergeAllCommandTest extends ConfigCommandTestCase
{
    protected string $defaultFormat = 'json';

    private function splitTheOriginals(): void
    {
        $this->writeOriginals();
        $tester = $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    private function outputDirectory(): string
    {
        return $this->temporaryDirectory() . '/merged';
    }

    public function testWithoutAnOutputDirectoryItOnlyReportsAndWritesNothing(): void
    {
        $this->splitTheOriginals();
        $before = $this->snapshot();

        $tester = $this->execute('app:config:merge-all');

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $data = self::documentOf($tester)['data'];
        $this->assertCount(6, $data['results']);
        $this->assertSame(0, $data['written']);
        $this->assertNull($data['outputDir']);
        $this->assertSame($before, $this->snapshot(), 'nothing is changed or added');
    }

    public function testItWritesTheFinalConfigOfEveryConfigAndTheyAreTheOriginals(): void
    {
        $this->splitTheOriginals();
        $output = $this->outputDirectory();

        $tester = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(6, self::documentOf($tester)['data']['written']);
        foreach (self::realConfigs() as $id => $original) {
            $this->assertFileExists($output . '/' . $id . '.json');
            $merged = $this->decode($output . '/' . $id . '.json');
            $this->assertSame(
                [],
                self::comparator()->differences(
                    self::normalizer()->normalize($original),
                    self::normalizer()->normalize($merged)
                ),
                $id
            );
            $this->assertFalse(property_exists($merged->metadata, 'parent'), "$id has no parent any more");
        }
        $this->assertCount(6, glob($output . '/*/*.json') ?: []);
        $this->assertFileDoesNotExist($output . '/generic.json', 'a parent is not a config: it is not written');
    }

    public function testCompleteConfigsAreWrittenInTheCurrentShape(): void
    {
        $this->writeOriginals(); // complete configs, in the old shape: CEL, SEL and MEL in datamodel
        $output = $this->outputDirectory();
        $before = $this->snapshot();

        $tester = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame($before, $this->snapshot(), 'the sources are not changed');
        foreach (self::realConfigs() as $id => $original) {
            $merged = $this->decode($output . '/' . $id . '.json');
            $this->assertTrue(property_exists($original->datamodel, 'CEL'), 'the original has the old shape');
            $this->assertTrue(property_exists($merged->datamodel, 'simulation_settings'), $id);
            foreach (['CEL', 'SEL', 'MEL'] as $simulation) {
                $this->assertFalse(property_exists($merged->datamodel, $simulation), "$id: $simulation moved");
                $this->assertTrue(property_exists($merged->datamodel->simulation_settings, $simulation), $id);
            }
        }
    }

    public function testTheDocumentTellsWhatWasWritten(): void
    {
        $this->splitTheOriginals();
        $output = $this->outputDirectory();

        $document = self::documentOf($this->execute('app:config:merge-all', ['--output-dir' => $output]));

        $this->assertSame($output, $document['data']['outputDir']);
        $this->assertSame($this->dir, $document['data']['root']);
        $result = $document['data']['results'][0];
        $this->assertSame('Baltic_Sea_basic/Baltic_Sea_basic_1', $result['id']);
        $this->assertSame('stripped', $result['kind']);
        $this->assertSame(['generic'], $result['parents']);
        $this->assertSame('Baltic_Sea_basic/Baltic_Sea_basic_1.json', $result['output']);
        $this->assertSame(filesize($output . '/' . $result['output']), $result['bytes']);
    }

    public function testItRefusesAnOutputDirectoryThatIsTheConfigDirectoryOrIsInIt(): void
    {
        $this->splitTheOriginals();
        $before = $this->snapshot();

        foreach ([$this->dir, $this->dir . '/merged', $this->dir . '/North_Sea_basic'] as $output) {
            $tester = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

            $this->assertSame(2, $tester->getStatusCode(), $output);
            $this->assertContains('invalid_usage', self::errorCodesOf($tester), $output);
        }
        $this->assertSame($before, $this->snapshot(), 'a source is never replaced');
    }

    public function testNothingIsWrittenWhenAParentIsMissing(): void
    {
        $this->splitTheOriginals();
        unlink($this->dir . '/generic.json');
        $output = $this->outputDirectory();

        $tester = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('parent_missing', self::errorCodesOf($tester));
        $this->assertCount(6, self::documentOf($tester)['errors'], 'every config that has the problem is told');
        $this->assertDirectoryDoesNotExist($output, 'nothing is written');
    }

    public function testFilesThatAreNoValidJsonAreSkippedInAReportAndStopAWrite(): void
    {
        $this->splitTheOriginals();
        file_put_contents($this->dir . '/Broken.json', '{ nope');
        $output = $this->outputDirectory();

        $report = $this->execute('app:config:merge-all');
        $write = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

        $this->assertSame(0, $report->getStatusCode());
        $this->assertContains('file_skipped', self::warningCodesOf($report));
        $this->assertSame(1, $write->getStatusCode());
        $this->assertSame(['file_not_json'], self::errorCodesOf($write));
        $this->assertDirectoryDoesNotExist($output, 'nothing is written');
    }

    public function testRunningItAgainReplacesTheFiles(): void
    {
        $this->splitTheOriginals();
        $output = $this->outputDirectory();
        $first = $this->execute('app:config:merge-all', ['--output-dir' => $output]);
        $afterFirst = $this->snapshot($output);
        file_put_contents($output . '/North_Sea_basic/North_Sea_basic_1.json', 'something else');

        $second = $this->execute('app:config:merge-all', ['--output-dir' => $output]);

        $this->assertSame(0, $first->getStatusCode());
        $this->assertSame(0, $second->getStatusCode());
        $this->assertSame($afterFirst, $this->snapshot($output), 'the same files as the first time');
    }

    public function testPatternSelectsTheConfigs(): void
    {
        $this->splitTheOriginals();
        $output = $this->outputDirectory();

        $document = self::documentOf(
            $this->execute('app:config:merge-all', ['--output-dir' => $output, '--pattern' => 'North_Sea_basic_*.json'])
        );

        $this->assertSame(['North_Sea_basic/North_Sea_basic_1'], array_column($document['data']['results'], 'id'));
        $this->assertCount(1, glob($output . '/*/*.json') ?: []);
    }
}
