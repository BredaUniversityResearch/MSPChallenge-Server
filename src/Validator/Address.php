<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Address extends Constraint
{
    public const INVALID_ADDRESS_ERROR = '01946f8f-46ab-7491-9275-6e6fd6a54a0a';

    protected const ERROR_NAMES = [
        self::INVALID_ADDRESS_ERROR => 'INVALID_ADDRESS_ERROR',
    ];

    public string $message = 'Invalid address. An address must be a hostname, a top-level domain (e.g., example.com), '.
        'a subdomain (e.g., sub.example.com), or a valid IPv4 or IPv6 address.';
    public bool $allowNull = false;

    public function __construct(
        ?array $options = null,
        ?bool $allowNull = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
        $this->allowNull = $allowNull ?? $this->allowNull;
    }
}
