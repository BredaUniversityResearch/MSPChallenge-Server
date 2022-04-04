<?php

namespace App\Domain\Common;

abstract class Enum
{
    use GetConstantsTrait;

    /**
     * int|string @var
     */
    private $value;

    /**
     * @param int|string $value
     */
    public function __construct($value)
    {
        assert(
            in_array($value, $this->getConstants()),
            'invalid value: ' . $value . ', possible values: ' . implode(',', $this->getConstants())
        );
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
