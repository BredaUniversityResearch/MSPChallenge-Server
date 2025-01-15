<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Game;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
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

#[Route('/api/Game')]
#[OA\Tag(name: 'Game', description: 'Operations related to game management')]
class GameController extends BaseController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    #[Route(
        path: '/CreatePOVConfig',
        name: 'session_api_game_create_pov_config',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Create POV Config',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: [
                        'region_bottom_left_x', 'region_bottom_left_y', 'region_top_right_x', 'region_top_right_y'
                    ],
                    properties: [
                        new OA\Property(property: 'region_bottom_left_x', type: 'number', example: 3920035),
                        new OA\Property(property: 'region_bottom_left_y', type: 'number', example: 3282700),
                        new OA\Property(property: 'region_top_right_x', type: 'number', example: 3930639),
                        new OA\Property(property: 'region_top_right_y', type: 'number', example: 3292502),
                        new OA\Property(
                            property: 'output_image_format',
                            type: 'string',
                            default: ConfigCreator::DEFAULT_IMAGE_FORMAT,
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'excl_layers_by_tags',
                            description: 'The layers to exclude from the export by tags. '.
                                'You can specify multiple tags to match for each layer. Format: json array of arrays',
                            type: 'string',
                            format: 'json',
                            default: null,
                            example: '[["ValueMap","Bathymetry"]]',
                            nullable: true
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'POV Config created successfully',
                content: new OA\MediaType(
                    mediaType: 'application/zip',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 400, description: 'Invalid region coordinates'),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'database exception response',
                            value: [
                                'success' => false,
                                'message' => 'Query exception: SQLSTATE[42S02]: Base table or view not found...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function createPOVConfig(
        Request $request,
        LoggerInterface $logger,
        // below is required by legacy to be auto-wired, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): StreamedResponse|JsonResponse {
        $sessionId = $this->getSessionIdFromRequest($request);
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
        } catch (\Exception $e) {
            return new JsonResponse(
                Router::formatResponse(
                    false,
                    'Could not set output image format, error: '.$e->getMessage(),
                    null,
                    __CLASS__,
                    __FUNCTION__
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $exclLayerByTags = null;
        try {
            if ($request->request->has('excl_layers_by_tags')) {
                $exclLayerByTags = json_decode(
                    $request->request->get('excl_layers_by_tags'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        } catch (\Exception $e) {
            return new JsonResponse(
                Router::formatResponse(
                    false,
                    'Invalid value for field excl_layers_by_tags, error: '.$e->getMessage(),
                    null,
                    __CLASS__,
                    __FUNCTION__
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $exclLayerByTags = is_array($exclLayerByTags) ? $exclLayerByTags : [];
        $configCreator->setExcludedLayersByTags(array_map(
            fn($s) => new LayerTags($s),
            $exclLayerByTags
        ));
        try {
            $zipFilepath = $configCreator->createAndZip($region);
        } catch (\Exception $e) {
            return new JsonResponse(
                Router::formatResponse(
                    false,
                    'Could not create POV config, error: '.$e->getMessage(),
                    null,
                    __CLASS__,
                    __FUNCTION__
                ),
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
        path: '/GetCountries',
        name: 'session_api_game_get_countries',
        methods: ['GET']
    )]
    #[OA\Get(
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of countries',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(
                                                property: 'country_id',
                                                type: 'integer'
                                            ),
                                            new OA\Property(
                                                property: 'country_name',
                                                type: 'string'
                                            ),
                                            new OA\Property(
                                                property: 'country_colour',
                                                type: 'string'
                                            ),
                                            new OA\Property(
                                                property: 'country_is_manager',
                                                type: 'boolean'
                                            )
                                        ],
                                        type: 'object'
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'Exception response',
                            value: [
                                'success' => false,
                                'message' => 'Error message'
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getCountries(
        Request $request,
        // below is required by legacy to be auto-wired
        APIHelper $apiHelper
    ): JsonResponse {
        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $countries = $game->GetCountries();
            return new JsonResponse(self::wrapPayloadForResponse(true, $countries));
        } catch (Exception $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 500);
        }
    }

    #[Route(
        path: '/GetActualDateForSimulatedMonth',
        name: 'session_api_game_get_actual_date_for_simulated_month',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Get actual date for given simulated month',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: [
                        'simulated_month'
                    ],
                    properties: [
                        new OA\Property(property: 'simulated_month', type: 'number', example: 16)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Year and month of the requested simulated month identifier',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'year', type: 'integer', example: 2019),
                        new OA\Property(property: 'month_of_year', type: 'integer', example: 5)
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'Exception response',
                            value: [
                                'success' => false,
                                'message' => 'Error message'
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getActualDateForSimulatedMonth(
        Request $request,
        // below is required by legacy to be auto-wired
        APIHelper $apiHelper
    ): JsonResponse {
        $simulatedMonth = $request->request->get('simulated_month');
        if (!is_numeric($simulatedMonth)) {
            return new JsonResponse(
                Router::formatResponse(false, 'Invalid or missing simulated month', null, __CLASS__, __FUNCTION__),
                Response::HTTP_BAD_REQUEST
            );
        }
        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $actualDate = $game->GetActualDateForSimulatedMonth($simulatedMonth);
            return new JsonResponse(self::wrapPayloadForResponse(true, $actualDate));
        } catch (Exception $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 500);
        }
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
