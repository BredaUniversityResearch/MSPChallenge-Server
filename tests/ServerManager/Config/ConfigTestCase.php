<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\Merge\ConfigComparator;
use App\Domain\Config\Merge\ConfigNormalizer;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Merge\RegionConfigStripper;
use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;
use App\Domain\Config\Split\GenericNameRegistry;
use App\Domain\Config\Split\SplitResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for the config split / merge / strip tests.
 *
 * The services under test are plain classes without dependencies, so they are created directly: unused
 * private services are removed from the container, which would make them unavailable to a test.
 * The kernel is only booted to find the project directory (and by the command tests).
 *
 * The complete configs the tests work on come from ConfigFixtures (the originals at a fixed git revision),
 * never from ServerManager/configfiles: that folder holds the stripped configs once they are applied.
 */
abstract class ConfigTestCase extends KernelTestCase
{
    /** Up to this size two strings are compared as they are, so a difference shows. */
    private const int READABLE_SIZE = 4000;

    private static ?string $projectDir = null;
    /** @var array{0: SplitResult, 1: GenericNameRegistry}|null */
    private static ?array $realSplit = null;

    protected static function projectDir(): string
    {
        if (self::$projectDir === null) {
            self::bootKernel();
            self::$projectDir = self::$kernel->getProjectDir();
            self::ensureKernelShutdown();
        }
        return self::$projectDir;
    }

    protected static function configDir(): string
    {
        return self::projectDir() . '/ServerManager/configfiles';
    }

    /**
     * The complete original configs (see ConfigFixtures), keyed "<folder>/<name>". Never modify them, use
     * ConfigFactory::copy().
     *
     * @return array<string, \stdClass>
     * @throws \JsonException
     * @throws \RuntimeException when the fixtures cannot be had: PHPUnit then reports an error
     */
    protected static function realConfigs(): array
    {
        return ConfigFixtures::configs(self::projectDir());
    }

    /**
     * The same files, byte for byte.
     *
     * @return array<string, string>
     */
    protected static function realConfigFiles(): array
    {
        return ConfigFixtures::raw(self::projectDir());
    }

    /**
     * The real configs, split in memory (nothing is read from or written to generic.json / *.region.json).
     *
     * @return array{0: SplitResult, 1: GenericNameRegistry}
     * @throws \JsonException
     */
    protected static function realSplit(): array
    {
        return self::$realSplit ??= self::split(self::realConfigs());
    }

    /**
     * @param array<string, \stdClass> $configs
     * @return array{0: SplitResult, 1: GenericNameRegistry}
     */
    protected static function split(array $configs, ?GenericNameRegistry $registry = null): array
    {
        $registry ??= new GenericNameRegistry();
        $registry->register($configs);
        return [new ConfigSplitter($registry)->split($configs), $registry];
    }

    protected static function merger(): RegionConfigMerger
    {
        return new RegionConfigMerger();
    }

    protected static function stripper(): RegionConfigStripper
    {
        return new RegionConfigStripper();
    }

    protected static function comparator(): ConfigComparator
    {
        return new ConfigComparator();
    }

    protected static function normalizer(): ConfigNormalizer
    {
        return new ConfigNormalizer();
    }

    /**
     * What a merged region config has to be equal to: the complete config in the new shape, without the
     * fields the design document removes.
     *
     * @return string[] differences
     * @throws \JsonException
     */
    protected static function differencesToOriginal(\stdClass $generic, \stdClass $region, \stdClass $original): array
    {
        return self::comparator()->differences(
            self::normalizer()->normalize($original),
            self::merger()->merge($generic, $region)
        );
    }

    /**
     * @throws \JsonException
     */
    protected static function canonical(mixed $value): string
    {
        return ConfigValues::canonical($value);
    }

    protected static function byteSize(mixed $value): int
    {
        return strlen(json_encode($value, JSON_UNESCAPED_SLASHES));
    }

    protected static function datamodelKeys(\stdClass $config): array
    {
        return array_map('strval', array_keys(ConfigValues::props($config->datamodel)));
    }

    /**
     * @throws \JsonException
     */
    /**
     * Compares two JSON values. Big values (the real configs are about a megabyte) are compared by fingerprint:
     * when assertSame() fails on two huge strings PHPUnit spends minutes computing a diff nobody can read. Use the
     * ConfigComparator to find out what differs.
     *
     * @throws \JsonException
     */
    protected function assertSameJson(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertSameContents(self::canonical($expected), self::canonical($actual), $message);
    }

    /**
     * Compares two strings; big ones by size and hash, so a failure is reported at once and is readable.
     */
    protected function assertSameContents(string $expected, string $actual, string $message = ''): void
    {
        if (strlen($expected) <= self::READABLE_SIZE && strlen($actual) <= self::READABLE_SIZE) {
            $this->assertSame($expected, $actual, $message);
            return;
        }
        $this->assertSame(self::fingerprint($expected), self::fingerprint($actual), $message);
    }

    /**
     * Compares two sets of files (path => contents) by path, size and hash.
     *
     * @param array<string, string> $expected
     * @param array<string, string> $actual
     */
    protected function assertSameFiles(array $expected, array $actual, string $message = ''): void
    {
        $this->assertSame(
            array_map(self::fingerprint(...), $expected),
            array_map(self::fingerprint(...), $actual),
            $message
        );
    }

    private static function fingerprint(string $contents): string
    {
        return sprintf('%d bytes, md5 %s', strlen($contents), md5($contents));
    }
}
