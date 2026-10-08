<?php

namespace App\Domain\Config;

use JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates a decoded (objects, not assoc arrays) session config against SessionConfigJSONSchema.json. The schema
 * describes the final config: CEL, SEL and MEL in datamodel.simulation_settings (see RegionConfigMerger). A config in
 * another shape has to be merged or normalized first.
 *
 * This is the one validator for session configs: the upload, the creation of a session and the validation of a
 * save all use it.
 */
final class SessionConfigValidator
{
    private ?\stdClass $schema = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/src/Domain/SessionConfigJSONSchema.json')]
        private readonly string $schemaPath
    ) {
    }

    /**
     * What the schema finds, and the rules the schema cannot say: a raster layer needs a layer_width and a
     * layer_height (the server downloads the raster from GeoServer with the height; other layers do not have them).
     *
     * @param int $limit stop after this many errors
     * @return string[] what is wrong, as "[datamodel.meta[3].layer_name] The property layer_name is required"; empty
     *         when the config is valid
     * @throws \JsonException
     */
    public function errors(\stdClass $config, int $limit = 100): array
    {
        $this->schema ??= json_decode(file_get_contents($this->schemaPath), false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $validator->validate($config, $this->schema);
        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $errors[] = sprintf('[%s] %s', $error['property'], $error['message']);
        }
        array_push($errors, ...$this->rasterLayerErrors($config));
        if (count($errors) > $limit) {
            $errors = array_slice($errors, 0, $limit);
            $errors[] = '... and more (only the first ' . $limit . ' are shown)';
        }
        return $errors;
    }

    /**
     * @return string[]
     */
    private function rasterLayerErrors(\stdClass $config): array
    {
        $layers = ($config->datamodel ?? null) instanceof \stdClass ? ($config->datamodel->meta ?? null) : null;
        $errors = [];
        foreach (is_array($layers) ? $layers : [] as $index => $layer) {
            if (!$layer instanceof \stdClass || ($layer->layer_geotype ?? null) !== 'raster') {
                continue;
            }
            foreach (['layer_width', 'layer_height'] as $key) {
                if (!isset($layer->{$key})) {
                    $errors[] = sprintf(
                        '[datamodel.meta[%d].%s] A raster layer needs a %s (the raster is downloaded with it)',
                        $index,
                        $key,
                        $key
                    );
                }
            }
        }
        return $errors;
    }

    /**
     * @throws InvalidSessionConfigException
     * @throws \JsonException
     */
    public function validate(\stdClass $config): void
    {
        $errors = $this->errors($config);
        if ($errors !== []) {
            throw new InvalidSessionConfigException($errors);
        }
    }
}
