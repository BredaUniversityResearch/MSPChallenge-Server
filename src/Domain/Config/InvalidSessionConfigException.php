<?php

namespace App\Domain\Config;

/**
 * A session config that cannot be used: it is not valid JSON, or it does not match the schema.
 */
final class InvalidSessionConfigException extends \RuntimeException
{
    /**
     * @param string[] $errors what is wrong, one message per problem
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode('; ', $errors));
    }

    /**
     * The first errors on one line, for a message that has to stay short.
     */
    public function summary(int $max = 3): string
    {
        $shown = implode('; ', array_slice($this->errors, 0, $max));
        $more = count($this->errors) - $max;
        return $more > 0 ? $shown . " (and $more more)" : $shown;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
