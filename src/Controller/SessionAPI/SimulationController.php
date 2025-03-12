<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\Simulation;
use App\Entity\Watchdog;
use App\Repository\SimulationRepository;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/{simulation}', requirements: ['simulation' => '[sS]imulation'])]
#[OA\Tag(name: 'Simulation', description: 'Operations related to simulation management')]
class SimulationController extends BaseController
{
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('/Upsert', name: 'session_api_simulation_upsert', methods: ['POST'])]
    #[OA\Post(
        summary: 'Update or insert simulations for given watchdog server',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    description: 'Array of simulation names and versions',
                    type: 'object',
                    example: [
                        'SunHours' => '1.0.0',
                        'MoonHours' => '1.0.0'
                    ],
                    additionalProperties: new OA\AdditionalProperties(
                        description: 'The version of the simulation',
                        type: 'string'
                    )
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'x-remove-previous',
                description: 'Remove all previous simulations for this server',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            )
        ],
        responses: [
            new OA\Response(
                response: 404,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'watchdog_server_not_found',
                            summary: 'Watchdog server not found',
                            value: ['status' => 'Watchdog server not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'missing_server_id',
                            summary: 'Server ID is required',
                            value: ['status' => 'Server ID is required']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 201,
                description: 'Simulations created',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulations_created',
                            summary: 'Simulations created',
                            value: ['status' => 'Simulations created']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Simulations updated',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulations_updated',
                            summary: 'Simulations updated',
                            value: ['status' => 'Simulations updated']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function upsert(Request $request): JsonResponse
    {
        try {
            $serverId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $watchdogRepo = $em->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => $serverId->toBinary()])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Watchdog server not found'),
                Response::HTTP_NOT_FOUND
            );
        }

        $simulations = $request->request->all();
        $simRepo = $em->getRepository(Simulation::class);
        $sims = collect($simRepo->findBy(['watchdog' => $watchdog]))
            ->keyBy(fn(Simulation $sim) => $sim->getName());
        $simsToUpdate = $sims->only(array_keys($simulations));
        $removePrevious = $request->headers->get('x-remove-previous');
        if ($removePrevious && filter_var($removePrevious, FILTER_VALIDATE_BOOLEAN)) {
            $simsToDelete = $sims->forget(array_keys($simulations))->all();
            foreach ($simsToDelete as $sim) {
                $em->remove($sim);
            }
        }
        $isUpdate = false;
        foreach ($simulations as $name => $version) {
            $sim = $simsToUpdate->get($name) ?? new Simulation();
            $sim
                ->setName($name)
                ->setVersion($simulations[$sim->getName()] ?: null)
                ->setWatchdog($watchdog);
            $em->persist($sim);
            $isUpdate |= $sim->getId() !== null;
        }
        $em->flush();

        return new JsonResponse(
            self::wrapPayloadForResponse(
                true,
                message: $isUpdate ? 'Simulations updated' : 'Simulations created'
            ),
            $isUpdate ? Response::HTTP_OK : Response::HTTP_CREATED
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/GetAll',
        name: 'session_api_simulation_get_all',
        methods: ['GET']
    )]
    #[OA\Get(
        summary: 'Get all simulations for given watchdog server',
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 404,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'watchdog_server_not_found',
                            summary: 'Watchdog server not found',
                            value: ['status' => 'Watchdog server not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'missing_server_id',
                            summary: 'Server ID is required',
                            value: ['status' => 'Server ID is required']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Simulations found',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulations_found',
                            summary: 'Simulations found',
                            value: [
                                'status' => 'Simulations found',
                                'data' => [
                                    [
                                        'id' => '123e4567-e89b-12d3-a456-426614174000',
                                        'name' => 'Simulation Name',
                                        'version' => '1.0.0'
                                    ]
                                ]
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function getAll(Request $request): JsonResponse
    {
        try {
            $serverId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $watchdogRepo = $em->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => $serverId])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Watchdog server not found'),
                Response::HTTP_NOT_FOUND
            );
        }
        $simRepo = $em->getRepository(Simulation::class);
        $simulations = $simRepo->findBy(['watchdog' => $watchdog]);
        return new JsonResponse(self::wrapPayloadForResponse(true, $simulations));
    }

    /**
     * @throws Exception
     */
    #[Route('/Delete', name: 'session_api_simulation_delete', methods: ['POST'])]
    #[OA\Post(
        summary: 'Delete simulations for given watchdog server',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'names',
                            description: 'Array of simulation names',
                            type: 'array',
                            items: new OA\Items(type: 'string', example: 'Simulation Name')
                        )
                    ]
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 404,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'watchdog_server_not_found',
                            summary: 'Watchdog server not found',
                            value: ['status' => 'Watchdog server not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'missing_names',
                            summary: 'Names are required',
                            value: ['status' => 'Names are required']
                        ),
                        new OA\Examples(
                            example: 'missing_server_id',
                            summary: 'Server ID is required',
                            value: ['status' => 'Server ID is required']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Simulations deleted',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulations_deleted',
                            summary: 'Simulations deleted',
                            value: ['status' => 'Simulations deleted']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function delete(Request $request): JsonResponse
    {
        try {
            $serverId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }

        $names = array_filter($request->request->all());
        if (empty($names)) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Names are required'),
                Response::HTTP_BAD_REQUEST
            );
        }

        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $watchdogRepo = $em->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => $serverId])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Watchdog server not found'),
                Response::HTTP_NOT_FOUND
            );
        }

        $simRepo = $em->getRepository(Simulation::class);
        foreach ($names as $name) {
            if (null !== $sim = $simRepo->findOneBy(['watchdog' => $watchdog, 'name' => $name])) {
                $em->remove($sim);
            }
        }

        $em->flush();
        return new JsonResponse(
            self::wrapPayloadForResponse(true, message: 'Simulations deleted')
        );
    }

    /**
     * @throws Exception
     */
    #[Route('/DeleteAll', name: 'session_api_simulation_delete_all', methods: ['POST'])]
    #[OA\Post(
        summary: 'Delete all simulations for given watchdog server',
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 404,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'watchdog_server_not_found',
                            summary: 'Watchdog server not found',
                            value: ['status' => 'Watchdog server not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'missing_server_id',
                            summary: 'Server ID is required',
                            value: ['status' => 'Server ID is required']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 200,
                description: 'All simulations deleted',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'all_simulations_deleted',
                            summary: 'All simulations deleted',
                            value: ['status' => 'All simulations deleted']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function deleteAll(Request $request): JsonResponse
    {
        try {
            $serverId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }

        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $watchdogRepo = $em->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => $serverId])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Watchdog server not found'),
                Response::HTTP_NOT_FOUND
            );
        }

        $simRepo = $em->getRepository(Simulation::class);
        $simulations = $simRepo->findBy(['watchdog' => $watchdog]);
        foreach ($simulations as $simulation) {
            $em->remove($simulation);
        }

        $em->flush();
        return new JsonResponse(
            self::wrapPayloadForResponse(true, message: 'All simulations deleted')
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/NotifyMonthSimulationFinished',
        name: 'session_api_simulation_notify_month_simulation_finished',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Notify that the monthly simulation has finished',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['simulation_name', 'month'],
                    properties: [
                        new OA\Property(
                            property: 'simulation_name',
                            description: 'The name of the simulation',
                            type: 'string',
                            example: 'Simulation Name'
                        ),
                        new OA\Property(
                            property: 'month',
                            description: 'The month for which the simulation has finished',
                            type: 'integer',
                            example: 1
                        )
                    ]
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Month simulation finish has been noted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Month simulation finished notified'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Simulation name is required')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Entity not found')
                    ]
                )
            )
        ]
    )]
    public function notifyMonthSimulationFinished(Request $request): JsonResponse
    {
        try {
            $watchdogServerId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $repo = $em->getRepository(Simulation::class);
        if (null === $simName = $request->request->get('simulation_name')) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Simulation name is required'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (null === $month = $request->request->get('month')) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Month is required'),
                Response::HTTP_BAD_REQUEST
            );
        }
        // check month is integer
        if (!is_numeric($month)) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Month must be an integer'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $month = (int)$month;
        try {
            /** @var SimulationRepository $repo */
            $repo->notifyMonthSimulationFinished($watchdogServerId, $simName, $month);
        } catch (EntityNotFoundException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse(
            self::wrapPayloadForResponse(true, message: 'Month simulation finished notified')
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/GetConfiguredSimulationTypes',
        name: 'session_api_simulation_get_configured_simulation_types',
        methods: ['POST']
    )]
    #[OA\Post(
        description: 'Get Configured Simulation Types (e.g. ["MEL" => "2.0.0", "SEL" => "2.0.0", "CEL" => "2.0.0"])',
        summary: 'Get Configured Simulation Types',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the type name of the simulations present in the current configuration.',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'configured_simulation_types',
                            summary: 'Configured simulation types',
                            value: [
                                'status' => 'success', 'data' => ['MEL' => '2.0.0', 'SEL' => '2.0.0', 'CEL' => '2.0.0']
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'game_list_not_found',
                            summary: 'Game list not found',
                            value: ['status' => 'Game list not found']
                        ),
                        new OA\Examples(
                            example: 'game_config_not_found',
                            summary: 'Game config not found',
                            value: ['status' => 'Game config not found']
                        ),
                        new OA\Examples(
                            example: 'data_model_not_found',
                            summary: 'Data model not found',
                            value: ['status' => 'Data model not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function getConfiguredSimulationTypes(
        Request $request,
        SimulationHelper $simulationHelper
    ): JsonResponse {
        try {
            $requiredSimulationTypes = $simulationHelper->getConfiguredSimulationTypes(
                $this->getSessionIdFromRequest($request)
            );
        } catch (Exception $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse(self::wrapPayloadForResponse(true, $requiredSimulationTypes));
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/GetWatchdogTokenForServer',
        name: 'session_api_simulation_get_watchdog_token_for_server',
        methods: ['POST']
    )]
    #[OA\Post(
        description: 'Get the watchdog token for the current server. Used for setting up debug bridge in simulations.',
        summary: 'Get Watchdog Token For Server',
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the watchdog token for the current server.',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'watchdog_token',
                            summary: 'Watchdog token',
                            value: ['status' => 'success', 'watchdog_token' => '1234567890']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Internal watchdog server not found',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'internal_watchdog_server_not_found',
                            summary: 'Internal watchdog server not found',
                            value: ['status' => 'Internal watchdog server not found']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function getWatchdogTokenForServer(Request $request): JsonResponse
    {
        try {
            $watchdogServerId = $this->getServerIdFromRequest($request);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        }
        $em = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        if (null === $watchdog =
            $em->getRepository(Watchdog::class)->findOneBy(['serverId' => $watchdogServerId])
        ) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    false,
                    message: 'Could not find watchdog with server id: ' . $watchdogServerId->toRfc4122()
                ),
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse(
            self::wrapPayloadForResponse(true, ['watchdog_token' => $watchdog->getToken()])
        );
    }
}
