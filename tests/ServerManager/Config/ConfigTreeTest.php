<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Configs and parents anywhere in a tree: the configs are found by what they are, a parent is found by its name
 * (in any folder), and what is written is written where it was. $this->dir is the root of the tree.
 */
class ConfigTreeTest extends ConfigCommandTestCase
{
    /**
     * Where each original config is put (a path below the root => the id of the original): in folders of any depth,
     * and one in the root itself.
     */
    private const array LAYOUT = [
        'NS/Digitwin/NS_DT_2026.json' => 'North_Sea_basic/North_Sea_basic_1',
        'NS/Digitwin/2000/NS_DT_2000.json' => 'North_Sea_OR_ELSE_basic/North_Sea_OR_ELSE_basic_1',
        'NS/NS.json' => 'Baltic_Sea_basic/Baltic_Sea_basic_1',
        'Other/Baltic/deep/er/b.json' => 'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1',
        'Med.json' => 'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1',
    ];

    private function putLayout(): void
    {
        $filesystem = new Filesystem();
        foreach (self::LAYOUT as $path => $id) {
            $filesystem->dumpFile($this->dir . '/' . $path, self::realConfigFiles()[$id]);
        }
        $filesystem->dumpFile($this->dir . '/NS/Digitwin/2000/NS_DT_2000_Notes.txt', 'notes about the 2000 version');
        $filesystem->dumpFile($this->dir . '/NS/settings.json', '{"some": "settings"}'); // JSON, but no config
    }

    private function runSplit(array $options = []): \Symfony\Component\Console\Tester\CommandTester
    {
        return $this->execute('app:config:split', $options + ['--skip-validation' => true]);
    }

    private function assertEveryConfigMergesBackToItsOriginal(): void
    {
        $originals = self::realConfigs();
        $directory = new ConfigDirectory($this->dir);
        foreach (self::LAYOUT as $path => $id) {
            $config = $directory->read($this->dir . '/' . $path);
            $final = self::merger()->merge(ConfigParents::fromDirectory($directory)->poolOf($config, $path), $config);
            $this->assertSame(
                [],
                self::comparator()->differences(self::normalizer()->normalize($originals[$id]), $final),
                $path
            );
        }
    }

    public function testConfigsAnywhereInATreeAreSplitWhereTheyAre(): void
    {
        $this->putLayout();
        $before = $this->snapshot();

        $tester = $this->runSplit(['--apply' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $after = $this->snapshot();
        $this->assertSame(
            ['generic.json'],
            array_values(array_diff(array_keys($after), array_keys($before))),
            'only the generic config is new, in the root'
        );
        $this->assertSame([], array_diff(array_keys($before), array_keys($after)), 'and no file is gone');
        $this->assertSame(
            $before['NS/Digitwin/2000/NS_DT_2000_Notes.txt'],
            $after['NS/Digitwin/2000/NS_DT_2000_Notes.txt'],
            'notes are left alone'
        );
        $this->assertSame($before['NS/settings.json'], $after['NS/settings.json'], 'other JSON is left alone');
        foreach (array_keys(self::LAYOUT) as $path) {
            $this->assertSame('generic', json_decode($after[$path])->metadata->parent, "$path is stripped in place");
        }
        $this->assertEveryConfigMergesBackToItsOriginal();
    }

    public function testASplitOfWhatIsSplitAlreadyChangesNothing(): void
    {
        $this->putLayout();
        $this->runSplit(['--apply' => true]);
        $before = $this->snapshot();

        $check = $this->runSplit(['--check' => true]);
        $again = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $check->getStatusCode(), $check->getDisplay());
        $this->assertSame(0, $again->getStatusCode(), $again->getDisplay());
        $this->assertStringContainsString('Nothing to write', self::text($again));
        $this->assertSame($before, $this->snapshot());
    }

    public function testTheGenericConfigIsFoundAndWrittenWhereItIs(): void
    {
        $this->putLayout();
        $this->runSplit(['--apply' => true]);
        new Filesystem()->mkdir($this->dir . '/NS/shared/deep');
        new Filesystem()->rename($this->dir . '/generic.json', $this->dir . '/NS/shared/deep/generic.json');
        // one more config that shares layers with the others: the generic config can change
        new Filesystem()->dumpFile(
            $this->dir . '/More/m.json',
            self::realConfigFiles()['Northern_Mozambique_Channel_basic/Northern_Mozambique_Channel_basic_1']
        );

        $tester = $this->runSplit(['--apply' => true, '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileDoesNotExist($this->dir . '/generic.json', 'not written as a second generic config');
        $this->assertFileExists($this->dir . '/NS/shared/deep/generic.json');
        $this->assertEveryConfigMergesBackToItsOriginal();
    }

    public function testMergingAConfigFindsItsParentInTheTreeThroughTheRoot(): void
    {
        $this->putLayout();
        $this->runSplit(['--apply' => true]);
        $target = $this->dir . '/merged.json.out';
        $id = self::LAYOUT['NS/Digitwin/2000/NS_DT_2000.json'];

        $tester = $this->execute(
            'app:config:merge',
            ['file' => $this->dir . '/NS/Digitwin/2000/NS_DT_2000.json', '--output' => $target]
        );

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[$id]),
            json_decode((string)file_get_contents($target))
        ));
    }

    public function testVerifyComparesATreeWithOriginalsThatAreInTheSameLayout(): void
    {
        $this->putLayout();
        $originals = $this->temporaryDirectory();
        foreach (self::LAYOUT as $path => $id) {
            new Filesystem()->dumpFile($originals . '/' . $path, self::realConfigFiles()[$id]);
        }
        $this->runSplit(['--apply' => true]);

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('All 5 config(s) give the original config', self::text($tester));
        $this->assertStringContainsString('NS/Digitwin/2000/NS_DT_2000', self::text($tester), 'ids are paths');
    }

    public function testTwoParentsWithTheSameNameAreAnErrorThatNamesBothAndNothingIsWritten(): void
    {
        $this->putLayout();
        $this->runSplit(['--apply' => true]);
        new Filesystem()->copy($this->dir . '/generic.json', $this->dir . '/NS/generic.json');
        $before = $this->snapshot();

        $merge = $this->execute('app:config:merge', ['file' => $this->dir . '/Med.json']);
        $split = $this->runSplit(['--apply' => true, '--force' => true]);

        foreach ([$merge, $split] as $tester) {
            $this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
            $text = self::text($tester);
            $this->assertStringContainsString('The parent "generic" is ambiguous', $text);
            $this->assertStringContainsString('NS/generic.json and generic.json have that name', $text);
        }
        $this->assertSame($before, $this->snapshot(), 'nothing is written');
    }
}
