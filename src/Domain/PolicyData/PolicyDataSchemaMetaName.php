<?php

namespace App\Domain\PolicyData;

enum PolicyDataSchemaMetaName: string
{
    case POLICY_GROUP = 'policy_group'; // enum PolicyGroup
    case POLICY_TYPE_NAME = 'policy_type_name'; // enum PolicyTypeName
    case POLICY_TARGET = 'policy_target'; // enum PolicyTar

    // array of choices to input, or a closure that returns on. The closure gets one parameter: the game session id.
    case FIELD_ON_INPUT_CHOICES = 'field_on_input_choices';
    case FIELD_ON_INPUT_BITWISE_HANDLING = 'field_on_input_bitwise_handling'; // boolean
    case FIELD_ON_INPUT_DESCRIPTION = 'field_on_input_description'; // string
    case FIELD_OBJECT_SCHEMA_CALLABLE = 'field_filters_schema_callable'; // Callable to return Schema
}
