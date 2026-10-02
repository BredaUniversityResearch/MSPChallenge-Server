<?php

namespace App\Domain\Config;

use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates a decoded (objects, not assoc arrays) full session config against SessionConfigJSONSchema.json.
 *
 * Same schema and library as CommonSessionHandler::validateGameConfig(), extracted so it can be used
 * outside a messenger handler (that method is protected and writes to the session log).
 * CommonSessionHandler can later delegate to this class.
 */
final class SessionConfigValidator
{
    private ?SchemaContract $schema = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/src/Domain/SessionConfigJSONSchema.json')]
        private readonly string $schemaPath
    ) {
    }

    /**
     * @throws InvalidValue
     * @throws \JsonException|Exception
     */
    public function validate(\stdClass $config): void
    {
        $this->schema ??= Schema::import(
            json_decode(file_get_contents($this->schemaPath), false, 512, JSON_THROW_ON_ERROR)
        );
        $this->schema->in($config);
    }
}
