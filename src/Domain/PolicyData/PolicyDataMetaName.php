<?php

namespace App\Domain\PolicyData;

enum PolicyDataMetaName: string
{
    case GROUP = 'group';
    case TYPE_NAME = 'type_name';
    case ON_INPUT_SHOW_LAYER_TYPES = 'on_input_show_layer_types';
    case ON_INPUT_BITWISE_HANDLING = 'on_input_bitwise_handling';
    case ON_INPUT_DESCRIPTION = 'on_input_description';
}
