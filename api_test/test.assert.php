<?php

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class Assert
{
    public const CONSTRAINT_INT = "is_numeric";
    public const CONSTRAINT_STRING = "is_string";
    public const CONSTRAINT_ARRAY = "is_array";

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExpectArrayValues(array $keyValuePair, string $constraint, array $keys)
    {
        if (!is_array($keyValuePair)) {
            throw new Exception("keyValuePair in the ExpectArrayValues is not an array.");
        }

        foreach ($keys as $index) {
            if (!isset($keyValuePair[$index])) {
                throw new Exception(
                    "Missing expected array value \"".$index."\". Input values: ".var_export($keyValuePair, true)
                );
            } elseif (!self::CheckType($keyValuePair[$index], $constraint)) {
                throw new Exception(
                    "Argument \"".$index."\" is not of expected type. Expected ".$constraint." got ".
                    gettype($keyValuePair[$index])."(".$keyValuePair[$index].")"
                );
            }
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExpectArray($value)
    {
        if (!self::CheckType($value, self::CONSTRAINT_ARRAY)) {
            throw new Exception("Expected value of type array, got ".gettype($value)." (".$value.")");
        }
        if (is_null($value)) {
            throw new Exception("Encountered null value");
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExpectStringValue($value, string $expectedValue = null)
    {
        if (!self::CheckType($value, self::CONSTRAINT_STRING)) {
            throw new Exception("Expected value of type string, got ".gettype($value)." (".$value.")");
        }
        if (!is_null($expectedValue) && $value !== $expectedValue) {
            throw new Exception("Expected \"$expectedValue\", got \"$value\"");
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExpectIntValue($value, int $expectedValue = null)
    {
        if (!self::CheckType($value, self::CONSTRAINT_INT)) {
            throw new Exception("Expected value of type integer, got ".gettype($value)." (".$value.")");
        }
        if (!is_null($expectedValue) && $value !== $expectedValue) {
            throw new Exception("Expected \"$expectedValue\", got \"$value\"");
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function CheckType($value, callable $constraint)
    {
        return $constraint($value);
    }
}
