<?php

namespace App\Validator;

use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidJsonValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidJson) {
            throw new UnexpectedTypeException($constraint, ValidJson::class);
        }
        if (!$value instanceof FileObject) {
            throw new UnexpectedTypeException($value, FileObject::class);
        }
        if (empty(trim($value))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value instanceof UploadedFile ?
                    $value->getClientOriginalName() : $value->getFilename())
                ->setParameter('{{ error }}', 'Empty JSON data')
                ->addViolation();
            return;
        }
        $fileContent = file_get_contents($value->getPathname());
        json_decode($fileContent);
        if (json_last_error() === JSON_ERROR_NONE) {
            return;
        }
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ string }}', $value instanceof UploadedFile ?
                $value->getClientOriginalName() : $value->getFilename())
            ->setParameter('{{ error }}', json_last_error_msg())
            ->addViolation();
    }
}
