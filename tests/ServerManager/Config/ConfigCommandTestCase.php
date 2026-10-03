<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Base class for the tests of the app:config:* commands. They run on temporary directories that hold a copy of
 * the original configs; ServerManager/configfiles itself is never touched.
 */
abstract class ConfigCommandTestCase extends ConfigTestCase
{
    /** The config root of the test: <folder>/<name>.json, and generic configs (parents) <name>.json */
    protected string $dir;
    /** @var string[] */
    private array $temporaryDirectories = [];
    private string|false $terminalWidth = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->temporaryDirectory();
        // The console wraps messages in blocks such as [OK] and [ERROR] at the width of the terminal (at most 120),
        // and a word that is longer than that is cut in two. The width is the one of the terminal the tests run in
        // (COLUMNS): make it the same everywhere, so no test depends on how wide the developer's window is.
        $this->terminalWidth = getenv('COLUMNS');
        putenv('COLUMNS=120');
    }

    protected function tearDown(): void
    {
        putenv($this->terminalWidth === false ? 'COLUMNS' : 'COLUMNS=' . $this->terminalWidth);
        new Filesystem()->remove($this->temporaryDirectories);
        parent::tearDown();
    }

    protected function temporaryDirectory(): string
    {
        // normalized: on Windows sys_get_temp_dir() has backslashes, and the commands print paths with slashes
        $directory = Path::normalize(sys_get_temp_dir()) . '/msp_config_test_' . bin2hex(random_bytes(6));
        new Filesystem()->mkdir($directory);
        return $this->temporaryDirectories[] = $directory;
    }

    /**
     * Puts the original configs, byte for byte, in $directory (default: the config root of the test).
     */
    protected function writeOriginals(?string $directory = null): void
    {
        foreach (self::realConfigFiles() as $id => $contents) {
            new Filesystem()->dumpFile(($directory ?? $this->dir) . '/' . $id . '.json', $contents);
        }
    }

    /**
     * Writes generic.json (with the layer names in it) as app:config:split would, and returns the generic config.
     *
     * @throws \JsonException
     */
    protected function writeGeneric(?string $directory = null): \stdClass
    {
        [$result] = self::realSplit();
        $target = new ConfigDirectory($directory ?? $this->dir);
        $target->write($target->genericPath(), $result->generic);
        return $result->generic;
    }

    /**
     * Writes a generic config <name>.json that has $parent as its parent and adds nothing: it makes a chain of two.
     *
     * @throws \JsonException
     */
    protected function writeChildGeneric(string $name, string $parent, ?string $directory = null): void
    {
        $child = ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');
        $child->metadata->parent = $parent;
        $target = new ConfigDirectory($directory ?? $this->dir);
        $target->write($target->parentPath($name), $child);
    }

    /**
     * A copy of the original configs, stripped against generic.json, in a new directory that has generic.json too.
     *
     * @return string the directory
     */
    protected function strippedCopy(): string
    {
        $directory = $this->temporaryDirectory();
        $this->execute(
            'app:config:strip',
            ['--output-dir' => $directory, '--parent' => ConfigDirectory::DEFAULT_GENERIC, '--skip-validation' => true]
        );
        return $directory;
    }

    /**
     * @param array<string, mixed> $input arguments and options, like CommandTester::execute()
     */
    protected function execute(string $command, array $input = []): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester(new Application(self::$kernel)->find($command));
        $tester->execute($input + ['--dir' => $this->dir], ['decorated' => false]);
        return $tester;
    }

    /**
     * The output of a command as one line of text. Symfony wraps long messages in blocks such as [ERROR] at 120
     * columns, so a phrase can end up on two lines: assert on this, never on getDisplay(), unless it is JSON.
     */
    protected static function text(CommandTester $tester): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * @return array<string, string> path relative to $directory => contents, of every file in it
     */
    protected function snapshot(?string $directory = null): array
    {
        $directory ??= $this->dir;
        $files = [];
        foreach (new Finder()->files()->in($directory)->ignoreDotFiles(false) as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
        }
        ksort($files);
        return $files;
    }

    /**
     * @throws \JsonException
     */
    protected function decode(string $path): \stdClass
    {
        return new ConfigDirectory(dirname($path))->read($path);
    }

    /**
     * Asserts that every config in $directory, merged with its parents, gives the original again.
     *
     * @throws \JsonException
     */
    protected function assertMergesBackToTheOriginals(?string $directory = null): void
    {
        $directory ??= $this->dir;
        $parents = ConfigParents::fromDirectory(new ConfigDirectory($directory));
        foreach (self::realConfigs() as $id => $original) {
            $config = $this->decode($directory . '/' . $id . '.json');
            $this->assertSame(
                [],
                self::differencesToOriginal($parents->poolOf($config, $id), $config, $original),
                $id
            );
        }
    }

    /**
     * Removes a port layer from the old-style SEL of a complete config that generic.json also has (the layer itself
     * stays). An old-style list replaces the generic one, so the config then lacks something the generic config
     * would add: stripping it would change its meaning.
     */
    protected function dropAGenericPortLayer(\stdClass $config, \stdClass $generic): void
    {
        [, $registry] = self::realSplit();
        $genericNames = array_map(
            static fn(\stdClass $port) => $port->layer_name,
            $generic->datamodel->simulation_settings->SEL->port_layers
        );
        $ports = $config->datamodel->SEL->port_layers;
        foreach ($ports as $index => $port) {
            if (in_array($registry->get($port->layer_name), $genericNames, true)) {
                unset($ports[$index]);
                $config->datamodel->SEL->port_layers = array_values($ports);
                return;
            }
        }
        $this->fail('the config has none of the port layers of generic.json');
    }

    protected function assertNoTemporaryFiles(?string $directory = null): void
    {
        $leftovers = array_filter(
            array_keys($this->snapshot($directory)),
            static fn(string $path) => (bool)preg_match('/\.tmp[0-9a-f]+$/', $path)
        );
        $this->assertSame([], array_values($leftovers), 'no temporary files may be left behind');
    }
}
