<?php

namespace App\Domain\Common;

use ReflectionException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * This class is context array builder to be used when calling Symfony's Serializer (de)normalize methods.
 *
 * Note that from Symfony 7, the Serializer component will have a context builder that will allow you to define
 *   the context, see: https://github.com/symfony/serializer/commit/c70fee8c625be80d4829a10cd7db299a0e018c26
 * So, this class uses bits and pieces of Symfony 7 Serializer code, until we can upgrade to Symfony 7.
 *
 * Additionally, given a class, it validates if the fields in the context array are valid fields in the class.
 */
class NormalizerContextBuilder
{
    private static array $classPropertiesCache = [];
    private array $context = [];

    public function __construct(
        private readonly string $className
    ) {
    }


    protected function with(string $key, mixed $value): static
    {
        $this->context = array_merge($this->context, [$key => $value]);
        return $this;
    }

    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * @throws ReflectionException
     */
    public function withCallbacks(?array $callbacks): static
    {
        $this->validateCallbacks($callbacks);
        return $this->with(AbstractNormalizer::CALLBACKS, $callbacks);
    }

    /**
     * Configures attributes to be skipped when normalizing an object tree.
     *
     * This list is applied to each element of nested structures.
     *
     * Eg: ['foo', 'bar']
     *
     * Note: The behaviour for nested structures is different from ATTRIBUTES
     * for historical reason. Aligning the behaviour would be a BC break.
     *
     * @param list<string>|null $ignoredAttributes
     */
    public function withIgnoredAttributes(?array $ignoredAttributes): static
    {
        return $this->with(AbstractNormalizer::IGNORED_ATTRIBUTES, $ignoredAttributes);
    }

    /**
     * @throws ReflectionException
     */
    private function validateCallbacks(?array $callbacks): void
    {
        if (empty($callbacks)) {
            return;
        }
        $invalidFields = array_diff(array_keys($callbacks), self::getClassProperties($this->className));
        if (empty($invalidFields)) {
            return;
        }
        throw new \RuntimeException(
            'Invalid property(s) found for class ' . $this->className . ': ' . implode(', ', $invalidFields)
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
        self::$classPropertiesCache[$className] = array_keys($reflectionClass->getDefaultProperties());
        return self::$classPropertiesCache[$className];
    }
}
