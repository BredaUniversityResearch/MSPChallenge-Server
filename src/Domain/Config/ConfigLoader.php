<?php

namespace App\Domain\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
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
 */
final class ConfigLoader
{
    private readonly ConfigDirectory $directory;
    private readonly RegionConfigMerger $merger;

    public function __construct(
        #[Autowire('%app.server_manager_config_dir%')]
        string $configDir,
        private readonly SessionConfigValidator $validator
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
        $config = $this->decode($contents);
        return $this->merger->merge($this->parents()->poolOf($config), $config, $warnings);
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
        return ConfigDirectory::encode($this->merge($contents));
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
        foreach (RegionConfigMerger::SIMULATIONS as $name) {
            if ($settings !== null && array_key_exists($name, $settings)) {
                if (!array_key_exists($name, $datamodel)) {
                    $datamodel[$name] = $settings[$name];
                }
            } elseif (array_key_exists($name, $datamodel)) {
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
}
