<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidJson extends Constraint
{
    public string $message = 'The file "{{ string }}" is not a valid JSON. Error: {{ error }}.';
}
