<?php

namespace App\Validator;

use App\Domain\API\v1\UserBase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use function App\isJsonObject;

class ContainsValidExternalUsersValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContainsValidExternalUsers) {
            throw new UnexpectedTypeException($constraint, ContainsValidExternalUsers::class);
        }
        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) to take care of that
        if (null === $value || '' === $value) {
            return;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }
        if (!isJsonObject($value)) {
            throw new UnexpectedValueException($value, 'json');
        }

        $valueAsObject = json_decode($value);
        if (isset($valueAsObject->admin) && isset($valueAsObject->region)
            && isset($valueAsObject->admin->provider) && isset($valueAsObject->admin->value)
            && isset($valueAsObject->region->provider) && isset($valueAsObject->region->value)
        ) {
            $this->validateUserValue($valueAsObject->admin, $constraint);
            $this->validateUserValue($valueAsObject->region, $constraint);
        } elseif (isset($valueAsObject->provider) && isset($valueAsObject->value)) {
            foreach ($valueAsObject->value as $teamValue) {
                if (!empty($teamValue)) {
                    $totalUsersArray[] = $teamValue;
                }
            }
            $totalUsersArray = array_unique($totalUsersArray ?? []);
            $totalUsersObject = (object) [
                'provider' => $valueAsObject->provider,
                'value' => implode('|', $totalUsersArray)
            ];
            $this->validateUserValue($totalUsersObject, $constraint);
        } else {
            throw new UnexpectedValueException($value, 'MSP Challenge password json');
        }
    }

    private function validateUserValue(object $providerValueObject, Constraint $constraint): void
    {
        if ($providerValueObject->provider != 'local' && !empty($providerValueObject->value)) {
            $originalUsersArray = explode('|', $providerValueObject->value);
            $result = UserBase::checkExists($providerValueObject->provider, $providerValueObject->value);
            if (!empty($result['notfound'])) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ submittedUsers }}', implode(', ', $originalUsersArray))
                    ->setParameter('{{ knownUsers }}', 'none')
                    ->setParameter('{{ provider }}', UserBase::getProviderName($providerValueObject->provider))
                    ->addViolation();
            } else {
                $foundUsersArray = explode('|', $result['found']);
                if (count($foundUsersArray) != count($originalUsersArray)) {
                    $this->context->buildViolation($constraint->message)
                        ->setParameter('{{ submittedUsers }}', implode(', ', $originalUsersArray))
                        ->setParameter('{{ knownUsers }}', implode(', ', $foundUsersArray))
                        ->setParameter('{{ provider }}', UserBase::getProviderName($providerValueObject->provider))
                        ->addViolation();
                }
            }
        }
    }
}
