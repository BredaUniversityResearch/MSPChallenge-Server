<?php

namespace App\Validator;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint as JsonSchemaConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SimulationSettingsValidator extends ConstraintValidator
{
    private string $jsonSchema = '{
        "$schema": "http://json-schema.org/draft-04/schema#",
        "type": "object",
        "properties": {
            "simulation_type": {
                "type": "string"
            },
            "kpis": {
                "type": "array",
                "items": [
                    {
                        "type": "object",
                        "properties": {
                            "categoryName": {
                                "type": "string"
                            },
                            "unit": {
                                "type": "string"
                            },
                            "valueDefinitions": {
                                "type": "array",
                                "items": [
                                    {
                                        "type": "object",
                                        "properties": {
                                            "valueName": {
                                                "type": "string"
                                            }
                                        },
                                        "required": [
                                            "valueName"
                                        ]
                                    }
                                ]
                            }
                        },
                        "required": [
                            "categoryName",
                            "unit",
                            "valueDefinitions"
                        ]
                    }
                ]
            }
        },
        "required": [
            "simulation_type",
            "kpis"
        ]
    }';

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SimulationSettings) {
            throw new UnexpectedTypeException($constraint, SimulationSettings::class);
        }
        if (empty(trim($value))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ errors }}', 'Empty JSON data')
                ->addViolation();
            return;
        }
        $validator = new Validator();
        $data = json_decode($value);
        $schema = json_decode($this->jsonSchema);
        $validator->validate($data, $schema, JsonSchemaConstraint::CHECK_MODE_APPLY_DEFAULTS);
        if (!$validator->isValid()) {
            $errors = array_map(function ($error) {
                return sprintf("[%s] %s", $error['property'], $error['message']);
            }, $validator->getErrors());

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ errors }}', implode(", ", $errors))
                ->addViolation();
        }
    }
}
