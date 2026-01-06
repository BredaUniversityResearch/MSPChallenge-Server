<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Helper\Util;
use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Entity\ServerManager\DockerApi;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\ServerManager\ImmersiveSessionType;
use App\Form\DynamicEntityFormType;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/{manager}/entity/{entityName}',
    requirements: [
        'manager' => 'manager|ServerManager',
        'entityName' => '.+'
    ],
    defaults: ['manager' => 'manager']
)]
class EntityController extends BaseController
{
    public static function getSupportedEntityClasses(): array
    {
        return [GameGeoServer::class, GameWatchdogServer::class, DockerApi::class, ImmersiveSessionType::class];
    }

    /**
     * @throws Exception
     */
    #[Route('/list', name: 'manager_entity_list')]
    public function entityList(string $entityName): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $entityClass = $this->getEntityClass($entityName);
        if (!in_array($entityClass, self::getSupportedEntityClasses())) {
            throw new \InvalidArgumentException("Entity name $entityName is not found or supported.");
        }

        $repository = $entityManager->getRepository($entityClass);
        $entityList = $repository->findAll();

        // Use reflection to find Toggleable properties
        $idPropertyName = null;
        $headers = [];
        $reflectionClass = new ReflectionClass($entityClass);

        $readonlyEntityIDs = [];
        if (null !== $attribute = Util::getClassAttribute($reflectionClass, AppMappings\ReadonlyIDs::class)) {
            /** @var AppMappings\ReadonlyIDs $attribute */
            $readonlyEntityIDs = $attribute->readonlyIDs;
        }
        foreach ($reflectionClass->getProperties() as $property) {
            if (null !== Util::getPropertyAttribute($property, ORM\Id::class)) {
                $idPropertyName = $property->getName();
            }
            if (null !== $attribute = Util::getPropertyAttribute($property, AppMappings\Property\TableColumn::class)) {
                $headers[$property->getName()] = $attribute;
            }
        }
        return $this->render('manager/entity/list.html.twig', [
            'entityName' => $entityName,
            'headers' => $headers,
            'entityList' => $entityList,
            'idPropertyName' => $idPropertyName,
            'readonlyEntityIDs' => $readonlyEntityIDs
        ]);
    }

    private function getEntityClass(string $entityName): string
    {
        $conf = $this->connectionManager->getServerEntityManagerConfig('whatever');
        $prefix = $conf['mappings']['ServerManager']['prefix'];
        return $prefix.'\\'.$entityName;
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{entityId}/toggle/{propertyName}',
        name: 'manager_entity_toggle_property',
        requirements: ['entityId' => '\d+', 'propertyName' => '.+'],
    )]
    public function entityToggleProperty(string $entityName, int $entityId, string $propertyName): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        if (null === $entity = $entityManager->getRepository($this->getEntityClass($entityName))->find($entityId)) {
            throw new \InvalidArgumentException("Entity with ID $entityId not found.");
        }
        $this->toggleBooleanProperty($entity, $propertyName);
        $entityManager->flush();
        return new Response(null, 204);
    }

    private function toggleBooleanProperty(
        object $entity,
        string $propertyName
    ): void {
        $reflection = new ReflectionClass($entity);

        // Check if the property exists
        if (!$reflection->hasProperty($propertyName)) {
            throw new \InvalidArgumentException("Property '$propertyName' does not exist in the entity.");
        }

        $property = $reflection->getProperty($propertyName);
        // Check if the property is a boolean
        $propertyType = $property->getType();
        /** @var ?\ReflectionNamedType $propertyType */
        if (!$propertyType || $propertyType->getName() !== 'bool') {
            throw new \InvalidArgumentException("Property '$propertyName' is not declared as a boolean.");
        }

        // Toggle the boolean value
        $currentValue = $property->getValue($entity);
        $property->setValue($entity, !$currentValue);
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{entityId}/form',
        name: 'manager_entity_form',
        requirements: ['entityId' => '\d+']
    )]
    public function entityForm(
        string $entityName,
        Request $request,
        int $entityId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $entityClass = $this->getEntityClass($entityName);
        if ($entityId != 0) {
            $entity = $entityManager->getRepository($entityClass)->find($entityId);
            if (null === $entity) {
                throw new \InvalidArgumentException("Entity with ID $entityId not found.");
            }
        } else {
            $entity = new $entityClass();
        }
        $form = $this->createForm(
            DynamicEntityFormType::class,
            $entity,
            [
                'data_class' => $entityClass,
                'action' => $this->generateUrl(
                    'manager_entity_form',
                    [
                        'entityName' => $entityName,
                        'entityId' => $entityId
                    ]
                )
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entity = $form->getData();
            if ($entityId == 0) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
        }
        return $this->render(
            'manager/entity/form.html.twig',
            [
                'entityName' => $entityName,
                'entityForm' => $form->createView(),
                'entityPlurals' => $entity->getPlurals()
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
