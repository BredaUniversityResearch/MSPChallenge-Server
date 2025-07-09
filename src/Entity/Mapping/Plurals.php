<?php

namespace App\Entity\Mapping;

#[\Attribute]
class Plurals
{
    public function __construct(
        public string $singular, // value for quantity = 1
        public string $plural,   // value for quantity > 1 (or default for zero)
        public ?string $zero = null // Optional value for quantity = 0
    ) {
    }

    public function getValue(int $quantity): string
    {
        if ($quantity === 0 && $this->zero !== null) {
            return $this->zero;
        }

        return $quantity === 1 ? $this->singular : $this->plural;
    }
}
