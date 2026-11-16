<?php

namespace App\Domain\Config;

/**
 * The outcome of checking an uploaded config: what is wrong with it, or what to store.
 */
final readonly class UploadCheck
{
    /**
     * @param string[] $errors
     */
    private function __construct(public array $errors, public ?string $contents)
    {
    }

    /**
     * @param string[] $errors
     */
    public static function invalid(array $errors): self
    {
        return new self($errors, null);
    }

    public static function valid(string $contents): self
    {
        return new self([], $contents);
    }

    public function isValid(): bool
    {
        return $this->contents !== null;
    }
}
