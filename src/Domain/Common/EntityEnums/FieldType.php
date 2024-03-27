<?php

namespace App\Domain\Common\EntityEnums;

use Doctrine\DBAL\Types\Types;

// maps to Doctrine\DBAL\Types\Types to enum
enum FieldType: string
{
    case ASCII_STRING         = 'ascii_string'; //Types::ASCII_STRING
    case BIGINT               = 'bigint'; //Types::BIGINT
    case BINARY               = 'binary'; //Types::BINARY
    case BLOB                 = 'blob'; //Types::BLOB
    case BOOLEAN              = 'boolean'; //Types::BOOLEAN
    case DATE_MUTABLE         = 'date'; //Types::DATE_MUTABLE
    case DATE_IMMUTABLE       = 'date_immutable'; //Types::DATE_IMMUTABLE
    case DATEINTERVAL         = 'dateinterval'; //Types::DATEINTERVAL
    case DATETIME_MUTABLE     = 'datetime'; //Types::DATETIME_MUTABLE
    case DATETIME_IMMUTABLE   = 'datetime_immutable'; //Types::DATETIME_IMMUTABLE
    case DATETIMETZ_MUTABLE   = 'datetimetz'; //Types::DATETIMETZ_MUTABLE
    case DATETIMETZ_IMMUTABLE = 'datetimetz_immutable'; //Types::DATETIMETZ_IMMUTABLE
    case DECIMAL              = 'decimal'; //Types::DECIMAL
    case FLOAT                = 'float'; //Types::FLOAT
    case GUID                 = 'guid'; //Types::GUID
    case INTEGER              = 'integer'; //Types::INTEGER
    case JSON                 = 'json'; //Types::JSON

    case SIMPLE_ARRAY   = 'simple_array'; //Types::SIMPLE_ARRAY
    case SMALLINT       = 'smallint'; //Types::SMALLINT
    case STRING         = 'string'; //Types::STRING
    case TEXT           = 'text'; //Types::TEXT
    case TIME_MUTABLE   = 'time'; //Types::TIME_MUTABLE
    case TIME_IMMUTABLE = 'time_immutable'; //Types::TIME_IMMUTABLE
}
