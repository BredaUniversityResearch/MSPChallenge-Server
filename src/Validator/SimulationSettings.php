<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class SimulationSettings extends Constraint
{
    public const INVALID_JSON_ERROR = 'invalid-json-error';

    protected const ERROR_NAMES = [
        self::INVALID_JSON_ERROR => 'INVALID_JSON_ERROR',
    ];

    public string $message = 'Invalid JSON data: {{ errors }}';

    public function __construct(
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
    }
}
