<?php

namespace App\Domain\PolicyData;

enum PolicyDataSchemaMetaName: string
{
    case POLICY_GROUP = 'policy_group'; // enum PolicyGroup
    case POLICY_TYPE_NAME = 'policy_type_name'; // enum PolicyTypeName
    case FIELD_ON_INPUT_SHOW_LAYER_TYPES = 'field_on_input_show_layer_types'; // boolean
    case FIELD_ON_INPUT_BITWISE_HANDLING = 'field_on_input_bitwise_handling'; // boolean
    case FIELD_ON_INPUT_DESCRIPTION = 'field_on_input_description'; // string
    case FIELD_OBJECT_SCHEMA_CALLABLE = 'field_filters_schema_callable'; // Callable to return Schema
}
