<?php

namespace App\Tests\ServerManager\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * The config tools are built into a phar from a part of the source of the server (the config commands, the config
 * domain, the schema). That part may not start to use something that the phar does not have: a class of the server
 * outside the config code, or a package that is not in build/config-tools/composer.json. `bin/build-config-tools
 * --check` finds that, offline and in a second: these tests make it fail in a normal test run, so it is found while it
 * is a small fix and not when the workflow builds the release.
 *
 * Building the phar and the executables (Box, composer, static PHP) is not tested here: the build script tries what it
 * built (see docs/building-config-tools.md), and the workflow runs it.
 */
class ConfigToolsBuildTest extends TestCase
{
    private string $project;

    /** @var string[] */
    private array $temporary = [];

    protected function setUp(): void
    {
        $this->project = dirname(__DIR__, 3);
        if (!is_file($this->project . '/bin/build-config-tools')) {
            $this->markTestSkipped('bin/build-config-tools is not in this project');
        }
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporary);
    }

    /**
     * @return array{0: int, 1: string} the exit code, and what the script said
     */
    private function check(string $root): array
    {
        $process = new Process([PHP_BINARY, $this->project . '/bin/build-config-tools', '--check', '--root=' . $root]);
        $process->run();
        return [(int)$process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * A project with only the files that the tools are made of, to change.
     */
    private function copyOfTheTools(): string
    {
        $copy = Path::normalize(sys_get_temp_dir()) . '/msp_config_build_' . bin2hex(random_bytes(6));
        $this->temporary[] = $copy;
        $filesystem = new Filesystem();
        $filesystem->copy($this->project . '/bin/config-tools', $copy . '/bin/config-tools');
        $filesystem->copy(
            $this->project . '/src/Domain/SessionConfigJSONSchema.json',
            $copy . '/src/Domain/SessionConfigJSONSchema.json'
        );
        $filesystem->mirror($this->project . '/src/ConfigTools', $copy . '/src/ConfigTools');
        $filesystem->mirror($this->project . '/src/Domain/Config', $copy . '/src/Domain/Config');
        foreach (glob($this->project . '/src/Command/Config*.php') ?: [] as $file) {
            $filesystem->copy($file, $copy . '/src/Command/' . basename($file));
        }
        return $copy;
    }

    private function addAUseTo(string $root, string $file, string $use): void
    {
        $path = $root . '/' . $file;
        $code = (string)file_get_contents($path);
        $changed = str_replace("\nnamespace ", "\nuse $use;\nnamespace ", $code);
        $this->assertNotSame($code, $changed, "$file has a namespace to put a use before");
        file_put_contents($path, $changed);
    }

    public function testTheConfigToolsAreSelfContained(): void
    {
        [$exit, $said] = $this->check($this->project);

        $this->assertSame(0, $exit, $said);
        $this->assertStringContainsString('self-contained', $said);
    }

    public function testAClassOfTheServerThatTheToolsDoNotHaveIsFound(): void
    {
        $copy = $this->copyOfTheTools();
        $this->assertSame(0, $this->check($copy)[0], 'the copy is fine as it is');

        $this->addAUseTo($copy, 'src/Command/ConfigListCommand.php', 'App\\Domain\\Helper\\Util');
        [$exit, $said] = $this->check($copy);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('src/Command/ConfigListCommand.php uses App\\Domain\\Helper\\Util', $said);
    }

    public function testAPackageThatIsNotInTheToolsIsFound(): void
    {
        $copy = $this->copyOfTheTools();
        $this->assertSame(0, $this->check($copy)[0], 'the copy is fine as it is');

        $package = 'Symfony\\Component\\HttpKernel\\KernelInterface';
        $this->addAUseTo($copy, 'src/Domain/Config/ConfigLoader.php', $package);
        [$exit, $said] = $this->check($copy);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString($package, $said);
        $this->assertStringContainsString('build/config-tools/composer.json', $said);
    }
}
