<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Game;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Router;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\MessageJsonResponse;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\SessionAPI\Game as GameEntity;
use App\Repository\SessionAPI\GameRepository;
use Exception;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use function App\await;

#[Route('/api/{game}', requirements: ['game' => '[gG]ame'])]
#[OA\Tag(
    name: 'Game',
    description: 'Operations related to game management.'
)]
#[OA\Parameter(
    name: 'game',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        default: 'game',
        enum: ['game', 'Game']
    )
)]
class GameController extends BaseController
{
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
        WatchdogCommunicator $watchdogCommunicator
    ): void {
        $state = new GameStateValue(strtolower($state));
        $em = ConnectionManager::getInstance()->getGameSessionEntityManager($sessionId);
        /** @var GameRepository $repo */
        $repo = $em->getRepository(GameEntity::class);
        $game = $repo->retrieve();
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
            return new MessageJsonResponse(
                status: Response::HTTP_BAD_REQUEST,
                message: 'Invalid region coordinates'
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
            return new MessageJsonResponse(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                message: 'Could not set output image format, error: '.$e->getMessage()
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
            return new MessageJsonResponse(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                message: 'Invalid value for field excl_layers_by_tags, error: '.$e->getMessage()
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
            return new MessageJsonResponse(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                message: 'Could not create POV config, error: '.$e->getMessage()
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
            $countries = $game->getCountries();
            return new JsonResponse($countries);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
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
            return new MessageJsonResponse(
                status: Response::HTTP_BAD_REQUEST,
                message: 'Invalid or missing simulated month'
            );
        }
        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $actualDate = $game->GetActualDateForSimulatedMonth($simulatedMonth);
            return new JsonResponse($actualDate);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
        }
    }

    #[Route(
        path: '/PolicySimSettings',
        name: 'session_api_game_policy_sim_settings',
        methods: ['GET']
    )]
    #[OA\Get(
        summary: 'Get policy and simulation settings',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Policy and simulation settings retrieved successfully',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'object',
                                    // phpcs:ignore
                                    example: '{"policy_settings":[{"all_country_approval":true,"stakeholder_pressure_cool_down":0.1,"fleet_info":{"gear_types":["Bottom trawl (otter, beam, seine)","Industrial and pelagic trawl","Drift and fixed nets"],"fleets":[{"//comment":"0 Bottom trawl (otter, beam, seine)","gear_type":"0","country_id":"-1","initial_fishing_distribution":[{"country_id":3,"effort_weight":54},{"country_id":4,"effort_weight":54}]},{"//comment":"1 Industrial and pelagic trawl","gear_type":"1","country_id":"-1","initial_fishing_distribution":[{"country_id":3,"effort_weight":406},{"country_id":4,"effort_weight":406}]},{"//comment":"2 Drift and fixed nets","gear_type":"2","country_id":"-1","initial_fishing_distribution":[{"country_id":3,"effort_weight":127},{"country_id":4,"effort_weight":127}]}]},"policy_type":"fishing"},{"policy_type":"shipping"},{"policy_type":"energy"}],"simulation_settings":[{"simulation_type":"MEL","content":{"modelfile":"MELdata/North Sea model for MSP.eiixml","mode":null,"rows":114,"columns":85,"cellsize":10000,"x_min":3463000,"y_min":3117000,"x_max":4313000,"y_max":4257000,"initialFishingMapping":0.5,"fishingDisplayScale":100,"pressures":[{"name":"Protection Bottom Trawl","layers":[{"name":"NS_Oil_Gas_Offshore_Instalations","influence":1,"construction":false},{"name":"NS_Pipelines","influence":1,"construction":false}],"policy_filters":{"fleets":["0"]}},{"name":"Protection Industrial Trawl","layers":[{"name":"NS_Oil_Gas_Offshore_Instalations","influence":1,"construction":false},{"name":"NS_Pipelines","influence":1,"construction":true}],"policy_filters":{"fleets":["1"]}},{"name":"Protection Nets","layers":[{"name":"NS_Pipelines","influence":1,"construction":true},{"name":"NS_Tidal_Farms","influence":1,"construction":true}],"policy_filters":{"fleets":["2"]}}],"fishing":[{"name":"Bottom Trawl","policy_filters":{"fleets":["0"]}},{"name":"Industrial and Pelagic Trawl","policy_filters":{"fleets":["1"]}},{"name":"Drift and Fixed Nets","policy_filters":{"fleets":["2"]}}],"outcomes":[{"name":"Flatfish","subcategory":"Biomass"},{"name":"Cod","subcategory":"Biomass"}],"ecologyCategories":[{"categoryName":"Biomass","categoryColor":"#4575B4FF","unit":"t/km2","valueDefinitions":[{"valueName":"Flatfish","valueColor":"#FF7272FF","unit":"t/km2","valueDependentCountry":-1},{"valueName":"Cod","valueColor":"#FFC773FF","unit":"t/km2","valueDependentCountry":-1}]},{"categoryName":"Fishery","categoryColor":"#FDAE61FF","unit":"t/km2","valueDefinitions":[{"valueName":"Drift and Fixed Nets Catch","valueColor":"#73FFBCFF","unit":"t/km2","valueDependentCountry":-1},{"valueName":"Industrial and Pelagic Trawl Catch","valueColor":"#73D9FFFF","unit":"t/km2","valueDependentCountry":-1},{"valueName":"Bottom Trawl Catch","valueColor":"#FF0079FF","unit":"t/km2","valueDependentCountry":-1}]}]}},{"simulation_type":"SEL","kpis":[{"categoryName":"General","categoryColor":"#00FF00FF","unit":"ship","generateValuesPerPort":null,"categoryValueType":"Sum","valueDefinitions":[{"valueName":"ShippingIntensity","valueColor":"#00FF00FF","unit":null,"valueDependentCountry":0},{"valueName":"ShippingRisk","valueColor":"#00FF00FF","unit":null,"valueDependentCountry":0}]},{"categoryName":"Shipping Income","categoryColor":"#00FFFFFF","unit":"ship","generateValuesPerPort":"ShippingIncome_","categoryValueType":"Sum","valueDefinitions":[{"valueName":"ShippingIncome_Aalborg","valueDisplayName":"Aalborg","valueColor":"0x0000ff","graphZero":0,"graphOne":1500,"unit":"ship","valueDependentCountry":9},{"valueName":"ShippingIncome_Aberdeen","valueDisplayName":"Aberdeen","valueColor":"0x0000ff","graphZero":0,"graphOne":1500,"unit":"ship","valueDependentCountry":3}],"countryDependentValues":true},{"categoryName":"Route Efficiency","categoryColor":"#00FFFFFF","unit":"%","generateValuesPerPort":"ShippingRouteEfficiency_","categoryValueType":"Average","valueDefinitions":[{"valueName":"ShippingRouteEfficiency_Aalborg","valueDisplayName":"Aalborg","valueColor":"0x0000ff","graphZero":0,"graphOne":1500,"unit":"%","valueDependentCountry":9},{"valueName":"ShippingRouteEfficiency_Aberdeen","valueDisplayName":"Aberdeen","valueColor":"0x0000ff","graphZero":0,"graphOne":1500,"unit":"%","valueDependentCountry":3}],"countryDependentValues":true}]},{"simulation_type":"CEL","grey_centerpoint_color":"#3D1C04FF","grey_centerpoint_sprite":"oilbarrel","green_centerpoint_color":"#18840AFF","green_centerpoint_sprite":"lightning"}]}'
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
    public function policySimSettings(
        Request $request,
        // below is required by legacy to be auto-wired
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $settings = $game->PolicySimSettings();
            return new JsonResponse($settings);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
        }
    }

    #[Route(
        path: '/IsOnline',
        name: 'session_api_game_is_online',
        methods: ['GET','POST']
    )]
    #[OA\Get(
        summary: 'Check if the session is online',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session is online',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'string',
                                    example: 'online'
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Post(
        summary: 'Check if the session is online',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session is online',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'string',
                                    example: 'online'
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function isOnline(): JsonResponse
    {
        return new JsonResponse('online');
    }

    #[Route(
        path: '/Meta',
        name: 'session_api_game_meta',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Get all layer meta data required for a game',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: [
                        'user'
                    ],
                    properties: [
                        new OA\Property(property: 'user', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'sort',
                            description: 'Whether to sort the layers by their display order',
                            type: 'boolean',
                            default: false,
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'onlyActiveLayers',
                            description: 'Whether to return only active layers',
                            type: 'boolean',
                            default: true,
                            nullable: true
                        )
                    ]
                )
            )
        ),
    )]
    public function meta(
        Request $request
    ): JsonResponse {
        // get user from post request
        $user = $request->request->get('user');
        if (!is_numeric($user)) {
            return new MessageJsonResponse(
                status: Response::HTTP_BAD_REQUEST,
                message: 'Invalid or missing user'
            );
        }
        $sort = $request->request->get('sort', 'false');
        $sort = filter_var($sort, FILTER_VALIDATE_BOOLEAN);
        $onlyActiveLayers = $request->request->get('onlyActiveLayers', 'true');
        $onlyActiveLayers = filter_var($onlyActiveLayers, FILTER_VALIDATE_BOOLEAN);

        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            return new JsonResponse($game->Meta($user, $sort, $onlyActiveLayers));
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
        }
    }

    #[Route(
        path: '/GetCurrentMonth',
        name: 'session_api_game_get_current_month',
        methods: ['GET', 'POST']
    )]
    public function getCurrentMonth(Request $request): JsonResponse
    {
        $game = new Game();
        $game->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            return new JsonResponse($game->GetCurrentMonth());
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
        }
    }
}
