<?php

namespace App\Tests\ServerManager\Config;

use App\ConfigTools\ConfigToolsApplication;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * The config tools on their own: the application of bin/config-tools, without the kernel of the server. $this->dir is
 * the folder that the tool is "run in" (its working directory).
 */
class ConfigToolsApplicationTest extends ConfigCommandTestCase
{
    private string $startedIn = '';

    protected function setUp(): void
    {
        parent::setUp();
        // the tool is started in a folder: relative paths are relative to where it is run, like in a shell
        $this->startedIn = (string)getcwd();
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->startedIn);
        parent::tearDown();
    }

    private function tool(): ApplicationTester
    {
        $application = new ConfigToolsApplication($this->dir);
        $application->setAutoExit(false);
        return new ApplicationTester($application);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function json(ApplicationTester $tool, string $command, array $input = []): array
    {
        $tool->run(['command' => $command, '--format' => 'json'] + $input);
        return json_decode($tool->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testItHasTheConfigCommandsAndNothingOfTheServer(): void
    {
        $application = new ConfigToolsApplication($this->dir);

        $commands = array_filter(array_keys($application->all()), static fn(string $name) => str_contains($name, ':'));

        $this->assertEqualsCanonicalizing([
            'app:config:list',
            'app:config:validate',
            'app:config:merge',
            'app:config:verify',
            'app:config:strip',
            'app:config:split',
        ], $commands);
        $this->assertSame('config-tools', $application->getName());
        $this->assertSame('dev', $application->getVersion(), 'a build puts the version of the release here');
    }

    public function testTheFolderItIsRunInIsTheDefaultConfigRoot(): void
    {
        $this->writeOriginals();
        new Filesystem()->dumpFile($this->dir . '/NS/deeper/generic.json', self::genericJson());

        $document = $this->json($this->tool(), 'app:config:list'); // no --dir

        $this->assertSame(0, $document['exitCode']);
        $this->assertSame(6, $document['data']['summary']['configs']);
        $this->assertSame(1, $document['data']['summary']['parents']);
        $this->assertSame('NS/deeper/generic.json', $document['data']['parents'][0]['path']);
    }

    public function testRelativePathsAreRelativeToTheWorkingDirectory(): void
    {
        $this->writeOriginals();
        $tool = $this->tool();
        $tool->run(['command' => 'app:config:split', '--apply' => true, '--skip-validation' => true]);
        $this->assertSame(0, $tool->getStatusCode(), $tool->getDisplay());

        $document = $this->json($tool, 'app:config:merge', [
            'file' => 'North_Sea_basic/North_Sea_basic_1.json',
            '--output' => 'merged/ns.json',
        ]);

        $this->assertSame(0, $document['exitCode'], json_encode($document['errors']));
        $this->assertSame($this->dir . '/merged/ns.json', $document['data']['output']);
        $this->assertFileExists($this->dir . '/merged/ns.json');
    }

    public function testItSplitsAndVerifiesWithoutAServer(): void
    {
        $this->writeOriginals();
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $tool = $this->tool();

        $split = $this->json($tool, 'app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $verify = $this->json($tool, 'app:config:verify', ['--original-dir' => $originals]);
        $validate = $this->json($tool, 'app:config:validate');

        $this->assertSame(0, $split['exitCode']);
        $this->assertSame(6, $split['data']['written']['configs']);
        $this->assertSame(0, $verify['data']['failed']);
        $this->assertSame(6, $validate['data']['valid']);
        $this->assertFileExists($this->dir . '/generic.json');
    }

    public function testAWrongFormatIsAMistakeInHowItIsUsed(): void
    {
        $tool = $this->tool();

        $tool->run(['command' => 'app:config:list', '--format' => 'xml']);

        $this->assertSame(2, $tool->getStatusCode());
    }

    public function testTheScriptRunsAsAProgramOfItsOwn(): void
    {
        $autoloader = self::projectDir() . '/vendor/autoload.php';
        if (!is_file($autoloader) || PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('bin/config-tools needs the vendor folder of the project and PHP 8.4');
        }
        $this->writeOriginals();
        $process = new Process(
            [PHP_BINARY, self::projectDir() . '/bin/config-tools', 'app:config:list', '--format=json'],
            $this->dir // run in the folder with the configs
        );
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $document = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('app:config:list', $document['command']);
        $this->assertSame(6, $document['data']['summary']['configs']);
        $this->assertSame('', $process->getErrorOutput());
    }

    public function testTheScriptReturnsTheExitCodeOfTheCommand(): void
    {
        $autoloader = self::projectDir() . '/vendor/autoload.php';
        if (!is_file($autoloader) || PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('bin/config-tools needs the vendor folder of the project and PHP 8.4');
        }
        $process = new Process(
            [PHP_BINARY, self::projectDir() . '/bin/config-tools', 'app:config:merge', 'nope.json', '--format=json'],
            $this->dir
        );
        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertSame('file_not_found', json_decode($process->getOutput(), true)['errors'][0]['code']);
    }
}
