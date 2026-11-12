<?php

namespace App\Tests\ServerManager\Config;

/**
 * app:config:normalize: every config in one fixed form, so that originals and merged configs can be compared as text,
 * and so that a split can start from the normalized copies.
 */
class ConfigNormalizeCommandTest extends ConfigCommandTestCase
{
    protected string $defaultFormat = 'json';

    private function splitTheOriginals(): void
    {
        $this->writeOriginals();
        $tester = $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * The order of the keys of the places where a text comparison shows it.
     *
     * @return array<string, mixed>
     */
    private static function keyOrders(\stdClass $config): array
    {
        $keys = static fn(mixed $object): array => is_object($object)
            ? array_map('strval', array_keys(get_object_vars($object)))
            : [];
        return [
            'top' => $keys($config),
            'metadata' => $keys($config->metadata),
            'datamodel' => $keys($config->datamodel),
            'simulations' => $keys($config->datamodel->simulation_settings),
            'restrictions' => $keys($config->datamodel->restrictions),
            'layers' => array_map($keys, $config->datamodel->meta),
        ];
    }

    public function testWithoutAnOutputDirectoryItOnlyReportsAndWritesNothing(): void
    {
        $this->writeOriginals();
        $before = $this->snapshot();

        $tester = $this->execute('app:config:normalize');

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $data = self::documentOf($tester)['data'];
        $this->assertCount(6, $data['results']);
        $this->assertSame(0, $data['written']);
        $this->assertSame($before, $this->snapshot(), 'nothing is changed or added');
    }

    public function testItWritesTheNormalizedFormOfEveryConfigAndTheSourcesStayAsTheyAre(): void
    {
        $this->writeOriginals(); // complete configs in the old shape
        $before = $this->snapshot();
        $output = $this->temporaryDirectory() . '/normalized';

        $tester = $this->execute('app:config:normalize', ['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(6, self::documentOf($tester)['data']['written']);
        $this->assertSame($before, $this->snapshot(), 'the sources are not changed');
        foreach (self::realConfigs() as $id => $original) {
            $written = $this->decode($output . '/' . $id . '.json');
            $this->assertSameJson(self::normalizer()->normalize($original), $written, $id);
            $this->assertFalse(property_exists($written->datamodel, 'CEL'), "$id: the old place of the simulations");
            foreach ($written->datamodel->meta as $layer) {
                $this->assertFalse(property_exists($layer, 'layer_information'), "$id: a documented removal");
            }
        }
    }

    public function testNormalizingTwiceChangesNothing(): void
    {
        $this->writeOriginals();
        $base = $this->temporaryDirectory();
        $this->execute('app:config:normalize', ['--output-dir' => $base . '/first']);

        $this->execute('app:config:normalize', ['--dir' => $base . '/first', '--output-dir' => $base . '/second']);

        $files = glob($base . '/first/*/*.json') ?: [];
        $this->assertCount(6, $files);
        foreach ($files as $file) {
            $this->assertSame(
                file_get_contents($file),
                file_get_contents(str_replace($base . '/first', $base . '/second', $file)),
                basename($file)
            );
        }
    }

    public function testAStrippedConfigIsMergedWithItsParentsFirst(): void
    {
        $this->splitTheOriginals();
        $output = $this->temporaryDirectory() . '/normalized';

        $tester = $this->execute('app:config:normalize', ['--output-dir' => $output]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        foreach (self::realConfigs() as $id => $original) {
            $written = $this->decode($output . '/' . $id . '.json');
            $this->assertSame(
                [],
                self::comparator()->differences(self::normalizer()->normalize($original), $written),
                $id
            );
            $this->assertFalse(property_exists($written->metadata, 'parent'), "$id has no parent any more");
        }
        $this->assertFileDoesNotExist($output . '/generic.json', 'a parent is not a config');
    }

    public function testTheOutputDirectoryCanNotBeInTheConfigDirectory(): void
    {
        $this->writeOriginals();

        $tester = $this->execute('app:config:normalize', ['--output-dir' => $this->dir . '/inside']);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertContains('invalid_usage', self::errorCodesOf($tester));
    }

    public function testTheNormalizedOriginalsAndTheMergedConfigsOfTheirSplitHaveTheSameKeyOrder(): void
    {
        $this->writeOriginals();
        $base = $this->temporaryDirectory();
        $this->execute('app:config:normalize', ['--output-dir' => $base . '/normalized']);
        $split = $this->execute('app:config:split', [
            '--dir' => $base . '/normalized',
            '--original-dir' => $this->dir,
            '--output-dir' => $base . '/split',
            '--skip-validation' => true,
        ]);
        $this->assertSame(0, $split->getStatusCode(), $split->getDisplay());

        $merge = $this->execute(
            'app:config:merge-all',
            ['--dir' => $base . '/split', '--output-dir' => $base . '/merged']
        );

        $this->assertSame(0, $merge->getStatusCode(), $merge->getDisplay());
        foreach (array_keys(self::realConfigs()) as $id) {
            $normalized = $this->decode($base . '/normalized/' . $id . '.json');
            $merged = $this->decode($base . '/merged/' . $id . '.json');
            $this->assertSame(self::keyOrders($normalized), self::keyOrders($merged), $id);
            $this->assertSame([], self::comparator()->differences($normalized, $merged), $id);
        }
    }
}
