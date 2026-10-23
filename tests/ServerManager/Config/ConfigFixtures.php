<?php

namespace App\Tests\ServerManager\Config;

use Symfony\Component\Filesystem\Filesystem;

/**
 * The complete configs the config tests work on: the six originals as they were in git revision
 * d6e5d75 (before they were stripped), so the tests do not depend on what ServerManager/configfiles
 * contains now.
 *
 * They are not in the repository: they are downloaded once from GitHub and cached as a zip in
 * var/test-fixtures/. Every file is checked against the SHA-256 recorded below, so a cache or download
 * can never silently differ from what the tests expect. Without internet, set MSP_CONFIG_FIXTURES_DIR to a
 * directory holding the same files (<folder>/<name>.json), for example a checkout of that revision.
 *
 * When the files cannot be had this throws, so PHPUnit reports an error (not a skip, not a failure): the
 * tests could not run, and the build must not look green because of that.
 */
final class ConfigFixtures
{
    public const string REVISION = 'd6e5d75195ac4bf25d10ef7cb4342659fa2d22a8';
    public const string ENV_DIRECTORY = 'MSP_CONFIG_FIXTURES_DIR';

    private const string BASE_URL = 'https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server';
    private const string PATH_IN_REPOSITORY = 'ServerManager/configfiles';

    /** config id => SHA-256 of the file */
    public const array CHECKSUMS = [
        'Baltic_Sea_basic/Baltic_Sea_basic_1' => '45e6bc1ea2062d86781c4da4b1b583c5bb4957d12eb5a5a2981df1b36737f10a',
        'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1' => '05f6f2ee025ba7c8b42dd650d5f36a0edc49aafac08e46d6329b7327242882d8',
        'North_Sea_OR_ELSE_basic/North_Sea_OR_ELSE_basic_1' => 'aa754b66d90b59452a4a74d2100fb7c74a83cb6d10e7edb5f15d4d2788ebebfd',
        'North_Sea_basic/North_Sea_basic_1' => 'f7b454fb577e8cb1af034fb525cca2d05948f2f0b0f164b01602569c63c7ef61',
        'Northern_Mozambique_Channel_basic/Northern_Mozambique_Channel_basic_1' => 'bda53713739c615ef5db07d833d8c90a72ba51c3f8480ba805c59302a8f088d2',
        'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1' => 'e1d71fadd9e54d189aa360618c726db4db1de2b53837d51840ec09ee436345f4',
    ];

    /** @var array<string, string>|null */
    private static ?array $raw = null;
    /** @var array<string, \stdClass>|null */
    private static ?array $decoded = null;
    private static ?\RuntimeException $failure = null;

    /**
     * The files exactly as they were committed.
     *
     * @return array<string, string> config id "<folder>/<name>" => file contents
     * @throws \RuntimeException when they cannot be had
     */
    public static function raw(string $projectDir): array
    {
        if (self::$failure !== null) {
            throw self::$failure; // do not try (and wait for) the download again for every test
        }
        try {
            return self::$raw ??= self::load($projectDir);
        } catch (\RuntimeException $e) {
            throw self::$failure = $e;
        }
    }

    /**
     * @return array<string, \stdClass> config id => decoded config; never modify these, copy them first
     * @throws \JsonException
     */
    public static function configs(string $projectDir): array
    {
        return self::$decoded ??= array_map(
            static fn(string $contents) => json_decode(
                ltrim($contents, "\xEF\xBB\xBF"),
                false,
                512,
                JSON_THROW_ON_ERROR
            ),
            self::raw($projectDir)
        );
    }

    public static function cachePath(string $projectDir): string
    {
        return $projectDir . '/var/test-fixtures/msp-configs-' . substr(self::REVISION, 0, 7) . '.zip';
    }

    /**
     * Reads <directory>/<folder>/<name>.json for every config and checks them.
     *
     * @return array<string, string>
     * @throws \RuntimeException
     */
    public static function fromDirectory(string $directory): array
    {
        $raw = [];
        foreach (array_keys(self::CHECKSUMS) as $id) {
            $path = $directory . '/' . $id . '.json';
            if (false === $contents = @file_get_contents($path)) {
                throw new \RuntimeException("Missing $path");
            }
            $raw[$id] = $contents;
        }
        return self::verified($raw);
    }

    /**
     * @param array<string, string> $raw
     * @return array<string, string> the same, when every file is the expected one
     * @throws \RuntimeException
     */
    public static function verified(array $raw): array
    {
        foreach (self::CHECKSUMS as $id => $checksum) {
            if (!isset($raw[$id])) {
                throw new \RuntimeException("Missing $id.json");
            }
            if (hash('sha256', $raw[$id]) !== $checksum) {
                throw new \RuntimeException(
                    "$id.json is not the file of revision " . substr(self::REVISION, 0, 7) . ' (checksum mismatch)'
                );
            }
        }
        return array_intersect_key($raw, self::CHECKSUMS);
    }

    /**
     * @return array<string, string>
     * @throws \RuntimeException
     */
    private static function load(string $projectDir): array
    {
        $directory = getenv(self::ENV_DIRECTORY);
        if ($directory !== false && $directory !== '') {
            return self::fromDirectory($directory);
        }
        $cache = self::cachePath($projectDir);
        try {
            if (is_file($cache)) {
                try {
                    return self::fromZip($cache);
                } catch (\RuntimeException) {
                    @unlink($cache); // damaged or from another revision: download again
                }
            }
            $raw = self::download();
            self::writeZip($cache, $raw);
            return $raw;
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf(
                "Cannot get the original configs the config tests work on (revision %s): %s\n"
                . "They are downloaded from %s/%s/%s/ and cached in %s.\n"
                . 'Without internet, set %s to a directory with the same files (<folder>/<name>.json), for example '
                . 'ServerManager/configfiles of a checkout of that revision.',
                substr(self::REVISION, 0, 7),
                $e->getMessage(),
                self::BASE_URL,
                substr(self::REVISION, 0, 7),
                self::PATH_IN_REPOSITORY,
                $cache,
                self::ENV_DIRECTORY
            ), 0, $e);
        }
    }

    /**
     * @return array<string, string>
     * @throws \RuntimeException
     */
    private static function download(): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('the curl extension is not available');
        }
        $raw = [];
        foreach (array_keys(self::CHECKSUMS) as $id) {
            $url = sprintf('%s/%s/%s/%s.json', self::BASE_URL, self::REVISION, self::PATH_IN_REPOSITORY, $id);
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => 'msp-config-test-fixtures',
            ]);
            $contents = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($contents === false || $status !== 200) {
                throw new \RuntimeException(
                    "download of $id.json failed (" . ($contents === false ? $error : "HTTP $status") . ')'
                );
            }
            $raw[$id] = $contents;
        }
        return self::verified($raw);
    }

    /**
     * @return array<string, string>
     * @throws \RuntimeException
     */
    private static function fromZip(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("cannot open $path");
        }
        $raw = [];
        foreach (array_keys(self::CHECKSUMS) as $id) {
            $contents = $zip->getFromName($id . '.json');
            if ($contents === false) {
                $zip->close();
                throw new \RuntimeException("$path lacks $id.json");
            }
            $raw[$id] = $contents;
        }
        $zip->close();
        return self::verified($raw);
    }

    /**
     * @param array<string, string> $raw
     * @throws \RuntimeException
     */
    private static function writeZip(string $path, array $raw): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($path));
        $temporary = $path . '.tmp' . bin2hex(random_bytes(4));
        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("cannot write $temporary");
        }
        foreach ($raw as $id => $contents) {
            $zip->addFromString($id . '.json', $contents);
        }
        if (!$zip->close()) {
            throw new \RuntimeException("cannot write $temporary");
        }
        $filesystem->rename($temporary, $path, true);
    }
}
