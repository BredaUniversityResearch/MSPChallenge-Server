<?php

namespace App\Domain\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the final config of a session config: the config file merged with its parents (see ConfigParents).
 *
 * Use merge() for a config file of the config folder (it may be stripped, naming its parent in metadata.parent, or
 * complete), and normalize() for a config that is final already (the running config of a session, the config in a
 * save): that one is not merged again, so it does not change when a parent does. Both give the shape the schema
 * describes, with CEL, SEL and MEL in simulation_settings. Code that still reads CEL, SEL and MEL directly from
 * datamodel can use withBothShapes() on the decoded config.
 *
 * The parent files are read at every call: a messenger worker lives long, and a parent can change.
 *
 * Merging a config takes some tens of milliseconds, and the same stored config is asked for again and again, so the
 * merged JSON of a config is cached (mergedJson(), which is what the stored configs are read with). The cache is
 * keyed by a fingerprint of the contents of the config, and an entry remembers the fingerprints of the parent files it
 * was made with: it is only used while all of those are unchanged. So it can not go stale, whatever happens to the
 * files or their modification times, and it is shared by every process that uses the same cache pool.
 */
final class ConfigLoader
{
    /** Part of the key of a cache entry: change it when the way an entry is made changes. */
    private const string CACHE_KEY_PREFIX = 'msp_config_merged_v2_';
    /** Entries of configs that changed or are gone are not removed, they expire. */
    private const int CACHE_LIFETIME_SECONDS = 604800;

    private readonly ConfigDirectory $directory;
    private readonly RegionConfigMerger $merger;

    /**
     * @param ?CacheItemPoolInterface $cache where merged configs are kept, null for no caching
     */
    public function __construct(
        #[Autowire('%app.server_manager_config_dir%')]
        string $configDir,
        private readonly SessionConfigValidator $validator,
        #[Autowire(service: 'config.cache')]
        private readonly ?CacheItemPoolInterface $cache = null
    ) {
        $this->directory = new ConfigDirectory(rtrim($configDir, '/\\'));
        $this->merger = new RegionConfigMerger();
    }

    /**
     * @throws InvalidSessionConfigException when the JSON is invalid, with the place of the error
     */
    public function decode(string $json): \stdClass
    {
        $json = (string)preg_replace('/^\xEF\xBB\xBF/', '', $json);
        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidSessionConfigException([
                'Invalid JSON, ' . (JsonSyntax::firstError($json) ?? lcfirst($e->getMessage()))
            ]);
        }
        if (!$decoded instanceof \stdClass) {
            throw new InvalidSessionConfigException(['The JSON is valid, but it is not an object']);
        }
        return $decoded;
    }

    /**
     * The final config of the contents of a config file: merged with its parents, when it has any.
     *
     * @param string[] $warnings filled with references to layers that are not in the config
     * @throws InvalidSessionConfigException
     * @throws ConfigParentException when a parent is missing or cannot be used
     */
    public function merge(string $contents, array &$warnings = []): \stdClass
    {
        return $this->mergeFingerprinted($contents, new \ArrayObject(), $warnings);
    }

    /**
     * @param \ArrayObject<string, array{path: string, fingerprint: string}> $fingerprints receives where the parent
     *        files that were used were found, and their fingerprints
     * @param string[] $warnings
     * @throws InvalidSessionConfigException
     * @throws ConfigParentException
     */
    private function mergeFingerprinted(string $contents, \ArrayObject $fingerprints, array &$warnings = []): \stdClass
    {
        $config = $this->decode($contents);
        $pool = ConfigParents::fromDirectory($this->directory, [], $fingerprints)->poolOf($config);
        return $this->merger->merge($pool, $config, $warnings);
    }

    /**
     * A config that is final already, in the shape of the schema, without using any parent.
     *
     * @throws InvalidSessionConfigException
     */
    public function normalize(string $contents): \stdClass
    {
        return RegionConfigMerger::toSimulationSettings($this->decode($contents));
    }

    /**
     * @throws InvalidSessionConfigException
     * @throws ConfigParentException
     * @throws \JsonException
     */
    public function mergedJson(string $contents): string
    {
        $key = self::CACHE_KEY_PREFIX . ConfigDirectory::fingerprint($contents);
        $cached = $this->cached($key);
        if ($cached !== null) {
            return $cached;
        }
        $fingerprints = new \ArrayObject();
        $json = ConfigDirectory::encode($this->mergeFingerprinted($contents, $fingerprints));
        $this->cache($key, $json, $fingerprints->getArrayCopy());
        return $json;
    }

    /**
     * @throws InvalidSessionConfigException
     * @throws ConfigParentException
     * @throws \JsonException
     * @throws \RuntimeException when the file cannot be read
     */
    public function mergedJsonOfFile(string $path): string
    {
        if (false === $contents = @file_get_contents($path)) {
            throw new \RuntimeException("Cannot read contents of the configuration file: $path");
        }
        return $this->mergedJson($contents);
    }

    /**
     * @return string[] what is wrong with the config, empty when it is valid
     * @throws \JsonException
     */
    public function errors(\stdClass $config): array
    {
        return $this->validator->errors($config);
    }

    /**
     * Checks an uploaded config: it has to be valid JSON, its parents (when it names any) have to be there, and its
     * final config has to match the schema. If it does, the result holds what to store: the final config, complete,
     * in the new shape. So whatever happens to the parents later, the stored config keeps working, and a config that
     * has a restricted parent never needs that parent to be kept on the server.
     *
     * @throws \JsonException
     */
    public function checkUpload(string $contents): UploadCheck
    {
        try {
            $config = $this->decode($contents);
            $merged = $this->merger->merge($this->parents()->poolOf($config, 'the uploaded config'), $config);
        } catch (InvalidSessionConfigException $e) {
            return UploadCheck::invalid($e->getErrors());
        } catch (ConfigParentException $e) {
            return UploadCheck::invalid([$e->getMessage()]);
        }
        $errors = $this->validator->errors($merged);
        return $errors === [] ? UploadCheck::valid(ConfigDirectory::encode($merged)) : UploadCheck::invalid($errors);
    }

    /**
     * Looks at the files of an upload (a config and the parents that come with it): is it complete, what is missing.
     *
     * @param array<string, string> $files the uploaded files: name => contents
     */
    public function inspectUpload(array $files): UploadInspection
    {
        $locator = new ParentLocator($this->directory); // one scan of the names for the whole upload
        return new UploadInspector(
            fn(string $json): \stdClass => $this->decode($json),
            fn(string $name): ?\stdClass => ConfigParents::readParent($this->directory, $name, null, $locator)
        )->inspect($files);
    }

    /**
     * Checks a complete upload of several files: the config, and the parents it needs that the server does not have.
     * Like checkUpload(), the result holds what to store: the final config, complete, in the new shape. The uploaded
     * parents are only used for that: they are not stored.
     *
     * @param array<string, string> $files the uploaded files: name => contents
     * @throws \JsonException
     */
    public function checkUploadFiles(array $files): UploadCheck
    {
        $inspection = $this->inspectUpload($files);
        if (!$inspection->isComplete() || $inspection->config === null) {
            return UploadCheck::invalid($inspection->errors !== [] ? $inspection->errors : [$inspection->summary()]);
        }
        try {
            $config = $this->decode($files[$inspection->config]);
            $uploaded = [];
            foreach ($inspection->parents as $file) {
                $uploaded[pathinfo($file, PATHINFO_FILENAME)] = $this->decode($files[$file]);
            }
            $pool = ConfigParents::fromDirectory($this->directory, $uploaded)->poolOf($config, 'the uploaded config');
            $merged = $this->merger->merge($pool, $config);
        } catch (InvalidSessionConfigException $e) {
            return UploadCheck::invalid($e->getErrors());
        } catch (ConfigParentException $e) {
            return UploadCheck::invalid([$e->getMessage()]);
        }
        $errors = $this->validator->errors($merged);
        return $errors === [] ? UploadCheck::valid(ConfigDirectory::encode($merged)) : UploadCheck::invalid($errors);
    }

    /**
     * Makes a decoded complete config (an assoc array with "datamodel") readable the old and the new way: CEL, SEL
     * and MEL directly in datamodel, and in datamodel.simulation_settings.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function withBothShapes(array $config): array
    {
        if (is_array($config['datamodel'] ?? null)) {
            $config['datamodel'] = self::datamodelWithBothShapes($config['datamodel']);
        }
        return $config;
    }

    /**
     * @param array<string, mixed> $datamodel
     * @return array<string, mixed>
     */
    public static function datamodelWithBothShapes(array $datamodel): array
    {
        $settings = is_array($datamodel['simulation_settings'] ?? null) ? $datamodel['simulation_settings'] : null;
        // the new shape also in the old one: every simulation can be read directly in datamodel too (when the
        // name is not taken by something else)
        foreach (array_keys($settings ?? []) as $name) {
            if (!array_key_exists($name, $datamodel)) {
                $datamodel[$name] = $settings[$name];
            }
        }
        // the old shape also in the new one: the simulations that old configs have directly in datamodel
        foreach (RegionConfigMerger::LEGACY_SIMULATIONS as $name) {
            if (array_key_exists($name, $datamodel) && !($settings !== null && array_key_exists($name, $settings))) {
                $settings ??= [];
                $settings[$name] = $datamodel[$name];
            }
        }
        if ($settings !== null) {
            $datamodel['simulation_settings'] = $settings;
        }
        return $datamodel;
    }

    private function parents(): ConfigParents
    {
        return ConfigParents::fromDirectory($this->directory);
    }

    /**
     * @return ?string the cached merged JSON, null when there is none or when a parent has changed since
     */
    private function cached(string $key): ?string
    {
        if ($this->cache === null) {
            return null;
        }
        try {
            $item = $this->cache->getItem($key);
            $entry = $item->isHit() ? $item->get() : null;
        } catch (\Throwable) {
            return null; // a cache that does not work is no reason not to load a config
        }
        if (!is_array($entry) || !is_string($entry['json'] ?? null) || !is_array($entry['dependencies'] ?? null)) {
            return null;
        }
        // the parent files the merge was made with, where they were found: all of them have to be unchanged. (A new
        // file with the name of one of them somewhere else is not noticed here: that is found when the config is
        // merged without the cache, and by verify.)
        foreach ($entry['dependencies'] as $name => $dependency) {
            if (!ConfigDirectory::isValidParentName((string)$name) || !is_array($dependency)
                || !is_string($dependency['path'] ?? null) || !is_string($dependency['fingerprint'] ?? null)
                || str_contains($dependency['path'], '..') || !str_ends_with($dependency['path'], '.json')) {
                return null;
            }
            $path = $this->directory->root() . '/' . $dependency['path'];
            clearstatcache(true, $path);
            $contents = @file_get_contents($path);
            if ($contents === false || ConfigDirectory::fingerprint($contents) !== $dependency['fingerprint']) {
                return null;
            }
        }
        return $entry['json'];
    }

    /**
     * @param array<string, array{path: string, fingerprint: string}> $dependencies where the parent files the merge was
     *        made with were found, and their fingerprints
     */
    private function cache(string $key, string $json, array $dependencies): void
    {
        if ($this->cache === null) {
            return;
        }
        try {
            $item = $this->cache->getItem($key);
            $item->set(['json' => $json, 'dependencies' => $dependencies]);
            $item->expiresAfter(self::CACHE_LIFETIME_SECONDS);
            $this->cache->save($item);
        } catch (\Throwable) {
            // see cached()
        }
    }
}
