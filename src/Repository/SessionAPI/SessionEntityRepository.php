<?php

namespace App\Repository\SessionAPI;

use App\Domain\Common\CustomMappingNameConvertor;
use App\Domain\Common\NormalizerContextBuilder;
use Doctrine\ORM\EntityRepository;
use Exception;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @template T of object
 * @extends EntityRepository<T>
 */
class SessionEntityRepository extends EntityRepository
{
    private ?ObjectNormalizer $normalizer = null; // to be created upon usage
    private ?Serializer $serializer = null; // to be created upon usage

    private ?CustomMappingNameConvertor $convertor = null;
    private ?NormalizerContextBuilder $normalizerContextBuilder = null;
    private ?NormalizerContextBuilder $denormalizerContextBuilder = null;

    /**
     * @param T $entity
     * @param bool $flush
     * @return void
     * @throws Exception
     */
    public function save(object $entity, bool $flush = false): void
    {
        $entityClass = $this->getClassName();
        if (!($entity instanceof $entityClass)) {
            throw new Exception('Entity must be of type '.$entityClass);
        }
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param T $entity
     * @param bool $flush
     * @return void
     * @throws Exception
     */
    public function remove(object $entity, bool $flush = false): void
    {
        $entityClass = $this->getClassName();
        if (!($entity instanceof $entityClass)) {
            throw new Exception('Entity must be of type '.$entityClass);
        }
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @overridable
     * This method is used to override and init the context builder for normalization, or set the default provided one
     */
    protected function initNormalizerContextBuilder(
        NormalizerContextBuilder $contextBuilder
    ): NormalizerContextBuilder {
        return $contextBuilder;
    }

    /**
     * @throws ReflectionException
     */
    private function createDefaultNormalizerContextBuilder(): NormalizerContextBuilder
    {
        $entityClass = $this->getClassName();
        $defaultNormalizerContextBuilder = new NormalizerContextBuilder();
        return $defaultNormalizerContextBuilder
            ->withClassPropertyValidation($entityClass);
    }

    /**
     * @throws ReflectionException
     */
    private function getNormalizerContextBuilder(): NormalizerContextBuilder
    {
        $this->normalizerContextBuilder ??= $this->initNormalizerContextBuilder(
            $this->createDefaultNormalizerContextBuilder()
        );
        return $this->normalizerContextBuilder;
    }

    /**
     * @overridable
     * This method is used to override and init the context builder for denormalization, or set the default provided one
     */
    protected function initDenormalizerContextBuilder(
        NormalizerContextBuilder $contextBuilder
    ): NormalizerContextBuilder {
        return $contextBuilder;
    }

    /**
     * @throws ReflectionException
     */
    private function createDefaultDenormalizerContextBuilder(): NormalizerContextBuilder
    {
        $entityClass = $this->getClassName();
        $defaultDenormalizerContextBuilder = new NormalizerContextBuilder();
        return $defaultDenormalizerContextBuilder
            ->withClassPropertyValidation($entityClass);
    }

    /**
     * @throws ReflectionException
     */
    private function getDenormalizerContextBuilder(): NormalizerContextBuilder
    {
        $this->denormalizerContextBuilder ??= $this->initDenormalizerContextBuilder(
            $this->createDefaultDenormalizerContextBuilder()
        );
        return $this->denormalizerContextBuilder;
    }

    /**
     * @overridable
     */
    protected function onPreDenormalize(array $data): array
    {
        return $data;
    }

    /**
     * @throws ExceptionInterface|ReflectionException
     */
    public function denormalize(array $data): object
    {
        $data = $this->onPreDenormalize($data);
        $entityClass = $this->getClassName();
        return $this->getSerializer()->denormalize(
            $data,
            $entityClass,
            null,
            $this->getDenormalizerContextBuilder()->toArray()
        );
    }

    /**
     * @param ?T $entity
     *
     * @throws Exception|ExceptionInterface
     */
    public function normalise(?object $entity): array
    {
        $entityClass = $this->getClassName();
        if (is_null($entity)) {
            return [];
        }
        if (!($entity instanceof $entityClass)) {
            throw new Exception('Entity must be of type '.$entityClass);
        }
        return $this->getSerializer()->normalize(
            $entity,
            null,
            $this->getNormalizerContextBuilder()->toArray()
        );
    }

    /**
     * @overridable
     * This method is used to override and init the convertor, or set the default provided one
     */
    protected function initNameConvertor(CustomMappingNameConvertor $convertor): CustomMappingNameConvertor
    {
        return $convertor;
    }

    private function getConvertor(): NameConverterInterface
    {
        $this->convertor ??= $this->initNameConvertor(
            new CustomMappingNameConvertor(defaultConverter: new CamelCaseToSnakeCaseNameConverter())
        );
        return $this->convertor;
    }

    protected function getNormalizer(): ObjectNormalizer
    {
        $this->normalizer ??= new ObjectNormalizer(null, $this->getConvertor());
        return $this->normalizer;
    }

    protected function getSerializer(): Serializer
    {
        $this->serializer ??= new Serializer(normalizers: [$this->getNormalizer()]);
        return $this->serializer;
    }
}
