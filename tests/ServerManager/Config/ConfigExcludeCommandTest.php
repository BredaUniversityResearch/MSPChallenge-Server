<?php

namespace App\Tests\ServerManager\Config;

use Symfony\Component\Filesystem\Filesystem;

/**
 * --exclude of the commands that read a config folder: what is left out is not seen at all, as a config or as a parent.
 */
class ConfigExcludeCommandTest extends ConfigCommandTestCase
{
    protected string $defaultFormat = 'json';

    /**
     * The six configs, and in NotMaintained a config that is not valid (like the old ones) and a stripped one whose
     * parent does not exist.
     */
    private function withANotMaintainedFolder(): void
    {
        $this->writeOriginals();
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->dir . '/NotMaintained/Broken.json',
            '{"metadata": {}, "datamodel": {"meta": [{"layer_name": "x"}]}}'
        );
        $filesystem->dumpFile(
            $this->dir . '/NotMaintained/Orphan.json',
            self::strippedJson('North_Sea_basic/North_Sea_basic_1', 'nowhere')
        );
    }

    public function testWithoutExcludeTheBrokenConfigsStopASplit(): void
    {
        $this->withANotMaintainedFolder();

        $tester = $this->execute('app:config:split', ['--output-dir' => $this->temporaryDirectory() . '/out']);

        $this->assertSame(1, $tester->getStatusCode());
        $files = array_column(self::documentOf($tester)['errors'], 'file');
        $this->assertContains('NotMaintained/Broken', $files);
    }

    public function testExcludeLeavesAFolderOutOfEveryCommandThatReadsTheFolder(): void
    {
        $this->withANotMaintainedFolder();
        $output = $this->temporaryDirectory() . '/out';
        $merged = $this->temporaryDirectory() . '/merged';
        $exclude = ['--exclude' => ['NotMaintained']];

        $list = self::documentOf($this->execute('app:config:list', $exclude));
        $validate = $this->execute('app:config:validate', $exclude);
        $split = $this->execute(
            'app:config:split',
            $exclude + ['--output-dir' => $output, '--skip-validation' => true]
        );
        $mergeAll = $this->execute('app:config:merge-all', $exclude + ['--output-dir' => $merged]);

        $this->assertSame(6, $list['data']['summary']['configs']);
        $this->assertSame(0, $validate->getStatusCode(), $validate->getDisplay());
        $this->assertSame(6, self::documentOf($validate)['data']['valid']);
        $this->assertSame(0, $split->getStatusCode(), $split->getDisplay());
        $this->assertSame(6, self::documentOf($split)['data']['written']['configs']);
        $this->assertDirectoryDoesNotExist($output . '/NotMaintained');
        $this->assertSame(0, $mergeAll->getStatusCode(), $mergeAll->getDisplay());
        $this->assertDirectoryDoesNotExist($merged . '/NotMaintained');
        $this->assertCount(6, glob($merged . '/*/*.json') ?: []);
    }

    public function testExcludeCanBeAPathAndCanBeGivenMoreThanOnce(): void
    {
        $this->withANotMaintainedFolder();

        $path = self::documentOf($this->execute('app:config:list', ['--exclude' => ['NotMaintained/*']]));
        $two = self::documentOf(
            $this->execute('app:config:list', ['--exclude' => ['NotMaintained/Broken', 'NotMaintained/Orphan.json']])
        );
        $one = self::documentOf($this->execute('app:config:list', ['--exclude' => ['NotMaintained/Broken']]));

        $this->assertSame(6, $path['data']['summary']['configs']);
        $this->assertSame(6, $two['data']['summary']['configs']);
        $this->assertSame(7, $one['data']['summary']['configs'], 'only what the pattern says');
    }

    public function testAParentInAnExcludedFolderIsNotAParent(): void
    {
        $this->writeOriginals();
        $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        copy($this->dir . '/generic.json', $this->dir . '/generic-copy.json');
        mkdir($this->dir . '/Old');
        rename($this->dir . '/generic-copy.json', $this->dir . '/Old/generic.json');
        $output = $this->temporaryDirectory() . '/merged';

        $twice = $this->execute('app:config:merge-all', ['--output-dir' => $output]);
        $excluded = $this->execute('app:config:merge-all', ['--output-dir' => $output, '--exclude' => ['Old']]);
        unlink($this->dir . '/generic.json');
        $missing = $this->execute('app:config:merge-all', ['--output-dir' => $output, '--exclude' => ['Old']]);

        $this->assertContains('parent_ambiguous', self::errorCodesOf($twice), 'two files have the name');
        $this->assertSame(0, $excluded->getStatusCode(), $excluded->getDisplay());
        $this->assertContains('parent_missing', self::errorCodesOf($missing), 'the one that is left is not there');
    }

    public function testAFileThatIsNamedIsNeverLeftOut(): void
    {
        $this->withANotMaintainedFolder();

        $tester = $this->execute(
            'app:config:validate',
            ['--exclude' => ['NotMaintained'], 'files' => [$this->dir . '/NotMaintained/Broken.json']]
        );

        $results = self::documentOf($tester)['data']['results'];
        $this->assertSame(['NotMaintained/Broken'], array_column($results, 'id'));
        $this->assertFalse($results[0]['valid'], 'it was asked for, so it is checked');
    }

    public function testWithoutExcludeEverythingIsSeen(): void
    {
        $this->withANotMaintainedFolder();

        $list = self::documentOf($this->execute('app:config:list'));

        $this->assertSame(8, $list['data']['summary']['configs']);
    }
}
