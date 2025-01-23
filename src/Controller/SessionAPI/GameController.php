<?php

namespace App\Controller\SessionAPI;

use App\Domain\Services\ConnectionManager;
use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Game;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\Game as GameEntity;
use Exception;
use OpenApi\Attributes as OA;
use App\Domain\Common\EntityEnums\GameStateValue;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Domain\Communicator\WatchdogCommunicator;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use function App\await;

#[Route('/api/Game')]
#[OA\Tag(name: 'Game', description: 'Operations related to game management')]
class GameController extends BaseController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    // not a route yet, should replace /[sessionId]/api/Game/State one day

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    public function state(
        int $sessionId,
        string $state,
        WatchdogCommunicator $watchdogCommunicator,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): void {
        $state = new GameStateValue(strtolower($state));
        $em = ConnectionManager::getInstance()->getGameSessionEntityManager($sessionId);
        /** @var GameEntity $game */
        $game = $em->getRepository(GameEntity::class)->retrieve();
        $currentState = $game->getGameState();
        if ($currentState == GameStateValue::END || $currentState == GameStateValue::SIMULATION) {
            throw new Exception("Invalid current state of ".$currentState);
        }
        if ($currentState == GameStateValue::SETUP) {
            //Starting plans should be implemented when we finish the SETUP phase (PAUSE, PLAY, FASTFORWARD request)
            $plan = new Plan();
            $plan->setGameSessionId($sessionId);
            await($plan->updateLayerState(0));
            if ($state == GameStateValue::PAUSE) {
                $game->setGameCurrentMonth(0);
            }
        }
        $game->setGameLastUpdate(microtime(true)); // note: not using mysql's UNIX_TIMESTAMP(NOW(6)) function here
        $game->setGameState($state);
        $em->flush();
        $watchdogCommunicator->changeState($sessionId, $state, $game->getGameCurrentMonth());
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
        } catch (Exception $e) {
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
                    ],
                    ref: '#/components/schemas/ResponseStructure'
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
                ref: '#/components/schemas/ResponseStructure',
                response: 200,
                description: 'Year and month of the requested simulated month identifier',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'year', type: 'integer', example: 2019),
                                new OA\Property(property: 'month_of_year', type: 'integer', example: 5)
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
                    ],
                    ref: '#/components/schemas/ResponseStructure'
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
}
