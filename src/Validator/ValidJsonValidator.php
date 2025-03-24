<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidJsonValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint \App\Validator\ValidJson */

        if (null === $value || '' === $value) {
            return;
        }

        $fileContent = file_get_contents($value->getPathname());
        json_decode($fileContent);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value->getClientOriginalName())
                ->addViolation();
        }
    }
}
