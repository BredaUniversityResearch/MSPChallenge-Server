<?php

namespace App\Tests\ServerManager\Config;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ConfigVerifyCommandTest extends ConfigCommandTestCase
{
    protected string $defaultFormat = 'json';

    private function stripAll(string $directory): void
    {
        $tester = $this->execute(
            'app:config:strip',
            ['--dir' => $directory, '--apply' => true, '--skip-validation' => true, '--parent' => 'generic']
        );
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @param string[] $arguments
     */
    private function git(string $repo, array $arguments): void
    {
        $process = new Process(
            array_merge(['git', '-c', 'user.name=test', '-c', 'user.email=test@example.com', '-C', $repo], $arguments)
        );
        $process->mustRun();
    }

    public function testStrippedConfigsAreComparedWithAnOriginalDirectory(): void
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->writeOriginals();
        $this->writeGeneric();
        $this->stripAll($this->dir);

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertTrue(self::documentOf($tester)['success']);
        $this->assertCount(6, self::documentOf($tester)['data']['results']);
        $this->assertSame(0, self::documentOf($tester)['data']['failed']);
        $this->assertNotContains(false, array_column(self::documentOf($tester)['data']['results'], 'same'));
    }

    public function testCompleteConfigsVerifyAgainstThemselves(): void
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->writeOriginals();
        $this->writeGeneric();

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    public function testAChangedConfigIsReportedWithItsDifferences(): void
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->writeOriginals();
        $this->writeGeneric();
        $this->stripAll($this->dir);
        $path = $this->dir . '/North_Sea_basic/North_Sea_basic_1.json';
        $stripped = $this->decode($path);
        $stripped->datamodel->meta[0]->layer_tooltip = 'changed after stripping';
        new Filesystem()->dumpFile($path, json_encode($stripped));

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains(false, array_column(self::documentOf($tester)['data']['results'], 'same'));
        $differences = array_merge(...array_column(self::documentOf($tester)['data']['results'], 'differences'));
        $this->assertNotSame([], preg_grep('/layer_tooltip differs/', $differences));
        $this->assertSame(['failed' => 1, 'total' => 6], self::documentOf($tester)['errors'][0]['details']);
    }

    public function testAMissingOriginalIsReported(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();
        $empty = $this->temporaryDirectory();

        $tester = $this->execute('app:config:verify', ['--original-dir' => $empty]);

        $this->assertSame(1, $tester->getStatusCode());
        $differences = array_merge(...array_column(self::documentOf($tester)['data']['results'], 'differences'));
        $this->assertNotSame([], preg_grep('/^Could not compare/', $differences));
    }

    public function testStrippedConfigsAreComparedWithTheirVersionInGit(): void
    {
        if ((new ExecutableFinder())->find('git') === null) {
            $this->markTestSkipped('git is not available.');
        }
        $repo = $this->temporaryDirectory();
        $root = $repo . '/ServerManager/configfiles';
        $this->writeOriginals($root);
        $this->git($repo, ['init', '-q']);
        $this->git($repo, ['add', '.']);
        $this->git($repo, ['commit', '-q', '-m', 'the complete configs']);
        $this->writeGeneric($root);
        $this->stripAll($root);

        $tester = $this->execute(
            'app:config:verify',
            ['--dir' => $root, '--repo' => $repo, '--against' => 'HEAD']
        );

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('git revision HEAD', self::documentOf($tester)['data']['source']);
        $this->assertTrue(self::documentOf($tester)['success']);
        $this->assertCount(6, self::documentOf($tester)['data']['results']);
        $this->assertSame(0, self::documentOf($tester)['data']['failed']);
    }

    public function testGitIsAskedForTheRightRevision(): void
    {
        if ((new ExecutableFinder())->find('git') === null) {
            $this->markTestSkipped('git is not available.');
        }
        $repo = $this->temporaryDirectory();
        $root = $repo . '/ServerManager/configfiles';
        $this->writeOriginals($root);
        $this->git($repo, ['init', '-q']);
        $this->git($repo, ['add', '.']);
        $this->git($repo, ['commit', '-q', '-m', 'the complete configs']);
        $this->git($repo, ['tag', 'originals']);
        $this->writeGeneric($root);
        $this->stripAll($root);
        $this->git($repo, ['add', '.']);
        $this->git($repo, ['commit', '-q', '-m', 'stripped']);

        $verify = static fn(string $revision): array => [
            '--dir' => $root,
            '--repo' => $repo,
            '--against' => $revision,
        ];
        $tester = $this->execute('app:config:verify', $verify('originals'));
        $notThere = $this->execute('app:config:verify', $verify('no-such-revision'));

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(1, $notThere->getStatusCode());
        $differences = array_merge(...array_column(self::documentOf($notThere)['data']['results'], 'differences'));
        $this->assertNotSame([], preg_grep('/git cannot show/', $differences));
    }

    public function testAConfigOutsideTheRepositoryIsReportedClearly(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();
        $otherPlace = $this->temporaryDirectory(); // not the folder the configs are in

        $tester = $this->execute('app:config:verify', ['--repo' => $otherPlace]);

        $this->assertSame(1, $tester->getStatusCode());
        $differences = array_merge(...array_column(self::documentOf($tester)['data']['results'], 'differences'));
        $this->assertNotSame([], preg_grep('/is not inside the repository/', $differences));
        $this->assertSame([], preg_grep('/cannot be made relative/', $differences));
    }

    public function testAMissingParentIsReportedForEachConfig(): void
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->writeOriginals();
        $this->writeGeneric();
        $this->stripAll($this->dir);
        unlink($this->dir . '/generic.json');

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(1, $tester->getStatusCode());
        $differences = array_merge(...array_column(self::documentOf($tester)['data']['results'], 'differences'));
        $this->assertNotSame([], preg_grep('/^Could not compare: The parent "generic" of/', $differences));
        $this->assertSame(['failed' => 6, 'total' => 6], self::documentOf($tester)['errors'][0]['details']);
    }

    public function testConfigsWithAChainOfParentsAreVerified(): void
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->writeOriginals();
        $this->writeGeneric();
        $this->writeChildGeneric('public', 'generic');
        $this->execute('app:config:strip', [
            '--apply' => true,
            '--parent' => 'public',
            '--skip-validation' => true,
        ]);

        $tester = $this->execute('app:config:verify', ['--original-dir' => $originals]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertTrue(self::documentOf($tester)['success']);
        $this->assertCount(6, self::documentOf($tester)['data']['results']);
        $this->assertSame(0, self::documentOf($tester)['data']['failed']);
    }
}
