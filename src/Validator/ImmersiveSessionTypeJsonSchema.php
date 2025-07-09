<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ImmersiveSessionTypeJsonSchema extends Constraint
{
    public string $message = 'The JSON data is invalid: {{ error }}';
}
