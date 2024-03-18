<?php

namespace App\Domain\Common\EntityEnums;

use Doctrine\DBAL\Types\Types;

// maps to Doctrine\DBAL\Types\Types to enum
enum FieldType: string
{
    case ASCII_STRING         = Types::ASCII_STRING;
    case BIGINT               = Types::BIGINT;
    case BINARY               = Types::BINARY;
    case BLOB                 = Types::BLOB;
    case BOOLEAN              = Types::BOOLEAN;
    case DATE_MUTABLE         = Types::DATE_MUTABLE;
    case DATE_IMMUTABLE       = Types::DATE_IMMUTABLE;
    case DATEINTERVAL         = Types::DATEINTERVAL;
    case DATETIME_MUTABLE     = Types::DATETIME_MUTABLE;
    case DATETIME_IMMUTABLE   = Types::DATETIME_IMMUTABLE;
    case DATETIMETZ_MUTABLE   = Types::DATETIMETZ_MUTABLE;
    case DATETIMETZ_IMMUTABLE = Types::DATETIMETZ_IMMUTABLE;
    case DECIMAL              = Types::DECIMAL;
    case FLOAT                = Types::FLOAT;
    case GUID                 = Types::GUID;
    case INTEGER              = Types::INTEGER;
    case JSON                 = Types::JSON;

    case SIMPLE_ARRAY   = Types::SIMPLE_ARRAY;
    case SMALLINT       = Types::SMALLINT;
    case STRING         = Types::STRING;
    case TEXT           = Types::TEXT;
    case TIME_MUTABLE   = Types::TIME_MUTABLE;
    case TIME_IMMUTABLE = Types::TIME_IMMUTABLE;
}
