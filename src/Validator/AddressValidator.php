<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AddressValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Address) {
            throw new UnexpectedTypeException($constraint, Address::class);
        }
        if ($constraint->allowNull && null === $value) {
            return;
        }
        if (!(is_string($value) &&
            (
                filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
                filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
            )
        )) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Address::INVALID_ADDRESS_ERROR)
                ->addViolation();
        }
    }
}
