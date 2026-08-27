<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GeoServerAccessType;
use App\Domain\Common\MessageJsonResponse;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
                'entityId' => $entityId,
                'entityForm' => $form->createView(),
                'entityPlurals' => $entity->getPlurals()
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route(
        '/{entityId}/test-connection',
        name: 'manager_entity_test_connection',
        requirements: ['entityId' => '\d+'],
        methods: ['POST']
    )]
    public function testConnection(
        string $entityName,
        int $entityId,
        Request $request,
        HttpClientInterface $httpClient
    ): MessageJsonResponse {
        if ($entityName !== 'GameGeoServer') {
            return new MessageJsonResponse(
                status: Response::HTTP_BAD_REQUEST,
                message: 'Test connection is not supported for this entity.'
            );
        }

        $formData = $request->request->all('dynamic_entity_form');
        $address = trim($formData['address'] ?? '');
        $accessType = $formData['accessType'] ?? GeoServerAccessType::CREDENTIALS->value;
        $username = trim((string)($formData['username'] ?? ''));
        $password = trim((string)($formData['password'] ?? ''));

        if (empty($address)) {
            return new MessageJsonResponse(status: Response::HTTP_BAD_REQUEST, message: 'Address is required.');
        }

        if ($accessType === GeoServerAccessType::CREDENTIALS->value && ($username === '' || $password === '')) {
            // When editing an existing entity the credential fields render blank (PasswordType
            // never pre-fills for security). Fall back to the stored DB values in that case.
            if ($entityId > 0) {
                $em = $this->connectionManager->getServerManagerEntityManager();
                /** @var GameGeoServer|null $storedEntity */
                $storedEntity = $em->getRepository(GameGeoServer::class)->find($entityId);
                if ($storedEntity !== null) {
                    if ($username === '') {
                        $username = $storedEntity->getUsername() ?? '';
                    }
                    if ($password === '') {
                        $password = $storedEntity->getPassword() ?? '';
                    }
                }
            }
            // Still no credentials after fallback → reject
            if ($username === '' || $password === '') {
                return new MessageJsonResponse(
                    status: Response::HTTP_BAD_REQUEST,
                    message: 'Username and password are required when access type is "Use credentials".'
                );
            }
        }

        // Normalise: ensure trailing slash
        if (!str_ends_with($address, '/')) {
            $address .= '/';
        }

        try {
            // Use WMS GetCapabilities as the test endpoint — it is available to all users
            // (anonymous and credentialed alike) and requires valid credentials when GeoServer
            // has security enabled, unlike /web/ which is publicly reachable.
            // max_redirects=0 ensures we catch servers that need the final HTTPS URL rather
            // than silently following a redirect that strips the Authorization header.
            $testUrl = $address.'ows?service=WMS&version=1.1.1&request=GetCapabilities';
            $options = [
                'timeout' => 10,
                'verify_peer' => false,
                'max_redirects' => 0,
            ];

            if ($accessType === GeoServerAccessType::CREDENTIALS->value) {
                $options['auth_basic'] = [$username, $password];
            }

            $response = $httpClient->request('GET', $testUrl, $options);
            $statusCode = $response->getStatusCode();

            // A redirect means the configured address needs to be the final URL
            // (e.g. http instead of https). Tell the user explicitly.
            if ($statusCode >= 300 && $statusCode < 400) {
                $location = $response->getHeaders(false)['location'][0] ?? '(unknown)';
                return new MessageJsonResponse(
                    status: Response::HTTP_BAD_REQUEST,
                    message: "GeoServer redirected to {$location}. "
                        .'Please configure the final URL (e.g. use https://) so credentials '
                        .'are not stripped by the redirect.'
                );
            }

            if (in_array($statusCode, [401, 403], true)) {
                return new MessageJsonResponse(
                    status: Response::HTTP_UNAUTHORIZED,
                    message: 'Authentication failed — invalid username/password or the account lacks access to WMS.'
                );
            }

            if ($statusCode < 400) {
                $layerCount = null;
                try {
                    $content = $response->getContent(false);
                    // WMS 1.1.1 GetCapabilities includes a DOCTYPE declaration that can make
                    // simplexml_load_string fail or attempt a remote DTD fetch. Strip it first.
                    $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
                    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
                    if ($xml !== false) {
                        // Named/publishable layers have a <Name> child; group containers do not.
                        $layerCount = count($xml->xpath('//Layer[Name]'));
                    }
                } catch (\Throwable) {
                    // Couldn't parse capabilities XML — success message still shows.
                }

                return new MessageJsonResponse(
                    data: $layerCount !== null ? ['advertisedLayerCount' => $layerCount] : null,
                    message: 'Connection successful.'
                );
            }

            return new MessageJsonResponse(
                status: Response::HTTP_BAD_GATEWAY,
                message: "GeoServer responded with HTTP {$statusCode}. Check address and credentials."
            );
        } catch (\Exception $e) {
            return new MessageJsonResponse(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                message: 'Connection error: '.$e->getMessage()
            );
        }
    }
}
