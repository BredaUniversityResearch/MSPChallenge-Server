<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidJson extends Constraint
{
    public $message = 'The file "{{ string }}" is not a valid JSON.';
}
