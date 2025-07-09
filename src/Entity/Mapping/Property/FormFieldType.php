<?php

namespace App\Entity\Mapping\Property;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FormFieldType
{
    public function __construct(
        public string $type,
        public array $options = [] // Optional: Additional options for the form field
    ) {
        if (!class_exists($type)) {
            throw new \InvalidArgumentException("Form field type class $type does not exist.");
        }
    }
}
