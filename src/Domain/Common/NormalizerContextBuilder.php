<?php

namespace App\Domain\Common;

use ReflectionException;
use Symfony\Component\Serializer\Context\Normalizer\AbstractObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class NormalizerContextBuilder extends AbstractObjectNormalizerContextBuilder
{
    public const CLASS_PROPERTY_VALIDATION = 'class_property_validation';

    private static array $classPropertiesCache = [];

    /**
     * @throws ReflectionException
     */
    public function withClassPropertyValidation(string $className): static
    {
        $instance = $this->with(self::CLASS_PROPERTY_VALIDATION, $className);
        $callbacks = $this->toArray()[AbstractNormalizer::CALLBACKS] ?? [];
        $this->validateCallbacks($callbacks);
        return $instance;
    }

    /**
     * @throws ReflectionException
     */
    public function withCallbacks(?array $callbacks): static
    {
        $this->validateCallbacks($callbacks);
        return parent::withCallbacks($callbacks);
    }

    /**
     * @throws ReflectionException
     */
    private function validateCallbacks(?array $callbacks): void
    {
        if (empty($callbacks)) {
            return;
        }
        if (empty($this->toArray()[self::CLASS_PROPERTY_VALIDATION])) {
            return;
        }
        $className = $this->toArray()[self::CLASS_PROPERTY_VALIDATION];
        if (!class_exists($className)) {
            throw new InvalidArgumentException('The class '.$className.' does not exist.');
        }
        $invalidFields = array_diff(array_keys($callbacks), self::getClassProperties($className));
        if (empty($invalidFields)) {
            return;
        }
        throw new \RuntimeException(
            'Invalid property(s) found for class '.$className.': '.implode(', ', $invalidFields)
        );
    }

    /**
     * @throws ReflectionException
     */
    private static function getClassProperties(string $className): array
    {
        if (isset(self::$classPropertiesCache[$className])) {
            return self::$classPropertiesCache[$className];
        }
        $reflectionClass = new \ReflectionClass($className);
        self::$classPropertiesCache[$className] = array_keys($reflectionClass->getProperties());
        return self::$classPropertiesCache[$className];
    }
}
