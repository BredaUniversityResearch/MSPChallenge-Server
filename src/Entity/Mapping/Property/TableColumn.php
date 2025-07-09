<?php

namespace App\Entity\Mapping\Property;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class TableColumn
{
    public function __construct(
        public ?string $label = null,
        public bool $action = false,
        public bool $toggleable = false,
        public bool $availability = false
    ) {
    }
}
