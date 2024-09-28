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

        $valueArray = json_decode($value, true);
        if (isset($valueArray['admin']) && isset($valueArray['region'])
            && isset($valueArray['admin']['provider']) && isset($valueArray['admin']['value'])
            && isset($valueArray['region']['provider']) && isset($valueArray['region']['value'])
        ) {
            $this->validateUserValue($valueArray['admin'], $constraint);
            $this->validateUserValue($valueArray['region'], $constraint);
            return;
        }
        if (isset($valueArray['provider']) && isset($valueArray['value'])) {
            foreach ($valueArray['value'] as $teamValue) {
                if (!empty($teamValue)) {
                    $totalUsersArray[] = $teamValue;
                }
            }
            $totalUsersArray = array_unique($totalUsersArray ?? []);
            $totalUsersArray = [
                'provider' => $valueArray['provider'],
                'value' => implode('|', $totalUsersArray)
            ];
            $this->validateUserValue($totalUsersArray, $constraint);
            return;
        }
        throw new UnexpectedValueException($value, 'MSP Challenge password json');
    }

    private function validateUserValue(array $providerValueArray, ContainsValidExternalUsers $constraint): void
    {
        if ($providerValueArray['provider'] != 'local' && !empty($providerValueArray['value'])) {
            $originalUsersArray = explode('|', $providerValueArray['value']);
            $result = UserBase::checkExists($providerValueArray['provider'], $providerValueArray['value']);
            if (!empty($result['notfound'])) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ submittedUsers }}', implode(', ', $originalUsersArray))
                    ->setParameter('{{ knownUsers }}', 'none')
                    ->setParameter('{{ provider }}', UserBase::getProviderName($providerValueArray['provider']))
                    ->addViolation();
                return;
            }
            $foundUsersArray = explode('|', $result['found']);
            if (count($foundUsersArray) != count($originalUsersArray)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ submittedUsers }}', implode(', ', $originalUsersArray))
                    ->setParameter('{{ knownUsers }}', implode(', ', $foundUsersArray))
                    ->setParameter('{{ provider }}', UserBase::getProviderName($providerValueArray['provider']))
                    ->addViolation();
                return;
            }
            $foundAlternativeUsersArray = array_diff($foundUsersArray, $originalUsersArray);
            $originalUsersWithAlternativeArray = array_diff($originalUsersArray, $foundUsersArray);
            if (!empty($foundAlternativeUsersArray)) {
                $this->context->buildViolation($constraint->messageAlternate)
                    ->setParameter('{{ userToCorrect }}', implode(', ', $originalUsersWithAlternativeArray))
                    ->setParameter('{{ knownUsers }}', implode(', ', $foundAlternativeUsersArray))
                    ->setParameter('{{ provider }}', UserBase::getProviderName($providerValueArray['provider']))
                    ->addViolation();
            }
        }
    }
}
