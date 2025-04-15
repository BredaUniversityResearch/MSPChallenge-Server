<?php

namespace App\Validator;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\ImmersiveSessionType;
use Exception;
use JsonSchema\Validator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ImmersiveSessionTypeJsonSchemaValidator extends ConstraintValidator
{
    public function __construct(private readonly ConnectionManager $connectionManager)
    {
    }

    /**
     * @throws Exception
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ImmersiveSessionTypeJsonSchema) {
            return;
        }

        // Retrieve the type from the entity being validated
        $type = $this->context->getObject()->getType()->value;

        // Fetch the JSON schema from the database based on the type
        $immersiveSessionType = $this->connectionManager->getServerManagerEntityManager()
            ->getRepository(ImmersiveSessionType::class)
            ->findOneBy(['type' => $type]);

        if (!$immersiveSessionType) {
            $this->context->buildViolation('Immersive session type not found: ' . $type)->addViolation();
            return;
        }

        // Validate the JSON data
        $validator = new Validator();
        $value = array_merge(
            $immersiveSessionType->getDataDefault(),
            $value
        );
        $validator->validate(
            $value,
            $immersiveSessionType->getDataSchema(),
            \JsonSchema\Constraints\Constraint::CHECK_MODE_TYPE_CAST
        );

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter(
                        '{{ error }}',
                        (empty($error['property'])?'':$error['property'].': ').$error['message']
                    )
                    ->addViolation();
            }
        }
    }
}
