<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use OpenApi\Attributes as OA;
use App\Entity\Watchdog;
use App\Repository\WatchdogRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class GameController extends BaseController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    public function createPOVConfig(
        int $sessionId,
        Request $request,
        LoggerInterface $logger,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): StreamedResponse|JsonResponse {
        $regionBottomLeftX = $request->request->get('region_bottom_left_x');
        $regionBottomLeftY = $request->request->get('region_bottom_left_y');
        $regionTopRightX = $request->request->get('region_top_right_x');
        $regionTopRightY = $request->request->get('region_top_right_y');
        if (!is_numeric($regionBottomLeftX) ||
            !is_numeric($regionBottomLeftY) ||
            !is_numeric($regionTopRightX) ||
            !is_numeric($regionTopRightY)) {
            return new JsonResponse(
                Router::formatResponse(false, 'Invalid region coordinates', null, __CLASS__, __FUNCTION__),
                Response::HTTP_BAD_REQUEST
            );
        }

        $region = new Region($regionBottomLeftX, $regionBottomLeftY, $regionTopRightX, $regionTopRightY);
        $configCreator = new ConfigCreator($this->projectDir, $sessionId, $logger);
        try {
            if ($request->request->has('output_image_format')) {
                $configCreator->setOutputImageFormat(
                    $request->request->get('output_image_format') ?: ConfigCreator::DEFAULT_IMAGE_FORMAT
                );
            }
            if ($request->request->has('excl_layers_by_tags')) {
                $exclLayerByTags = json_decode(
                    $request->request->get('excl_layers_by_tags'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $exclLayerByTags = is_array($exclLayerByTags) ? $exclLayerByTags : [];
                $configCreator->setExcludedLayersByTags(array_map(
                    fn($s) => new LayerTags($s),
                    $exclLayerByTags
                ));
            }
            $zipFilepath = $configCreator->createAndZip($region);
        } catch (\Exception $e) {
            return new JsonResponse(
                Router::formatResponse(false, $e->getMessage(), null, __CLASS__, __FUNCTION__),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Create a StreamedResponse
        $response = new StreamedResponse(function () use ($zipFilepath) {
            $fileStream = fopen($zipFilepath, 'rb');
            fpassthru($fileStream);
            fclose($fileStream);

            $filesystem = new Filesystem();
            $filesystem->remove($zipFilepath);
        });

        // Set response headers
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($zipFilepath)
        ));
        $response->headers->set('Content-Length', (string)filesize($zipFilepath));

        return $response;
    }

    #[Route(
        path: '/RegisterWatchdog',
        name: 'session_api_game_register_watchdog',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Register a new watchdog',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['server_id', 'address'],
                    properties: [
                        new OA\Property(
                            property: 'server_id',
                            type: 'string',
                            example: '9eec31f5-8701-474a-95c4-8f8f9ebe2785'
                        ),
                        new OA\Property(property: 'address', type: 'string', example: 'example.com'),
                        new OA\Property(property: 'port', type: 'integer', default: 80, example: 80),
                        new OA\Property(property: 'scheme', type: 'string', default: 'http', example: 'http')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Watchdog registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request parameters',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Invalid value for field address.'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Could not register watchdog. Error message'
                        )
                    ]
                )
            )
        ]
    )]
    public function registerWatchdog(
        Request $request,
        ConnectionManager $connectionManager
    ): JsonResponse {
        // check required POST parameters: server_id, address
        if ((null === $serverId = $request->request->get('server_id'))) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Missing required parameter server_id.'),
                Response::HTTP_BAD_REQUEST
            );
        }
        // convert server_id field to Symfony UUID
        try {
            is_string($serverId) or throw new \InvalidArgumentException(
                'String value is required.'
            );
            if ($serverId == Watchdog::getInternalServerId()->toRfc4122()) {
                return new JsonResponse(
                    self::wrapPayloadForResponse(
                        false,
                        message: 'Internal server ID is reserved.'
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }
            $serverId = Uuid::fromString($serverId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Invalid value for field server_id. '.$e->getMessage()
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
        if ((null === $address = $request->request->get('address'))) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Missing required parameter address.'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (!(is_string($address) &&
            (
                filter_var($address, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
                filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
            )
        )) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Invalid value for field address. Needs to be a valid domain or IP address.'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
        // check optional POST parameters: port and scheme
        if (($port = $request->request->get('port', 80)) && !is_numeric($port)) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Invalid value for field port. Numeric value is required.'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (($scheme = $request->request->get('scheme', 'http')) && !in_array($scheme, ['http', 'https'])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Invalid value for field scheme. Allowed values are http or https.'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $em = $connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
            /** @var WatchdogRepository $repo */
            $repo = $em->getRepository(Watchdog::class);
            $repo->registerWatchdog(
                $serverId,
                $address,
                (int)$port,
                $scheme
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Could not register watchdog. '.$e->getMessage()
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new JsonResponse(self::wrapPayloadForResponse(true, message: 'Watchdog registered successfully'));
    }
}
