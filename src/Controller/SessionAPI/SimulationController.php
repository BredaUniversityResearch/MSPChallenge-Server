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
use Symfony\Component\Uid\Uuid;

#[Route('/api/Simulation?')]
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
        summary: 'Update or insert a simulation for given watchdog server',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: [
                        'server_id', 'name'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'server_id',
                            description: 'Watchdog server ID',
                            type: 'string',
                            format: 'uuid',
                            example: '123e4567-e89b-12d3-a456-426614174000'
                        ),
                        new OA\Property(
                            property: 'name',
                            description: 'Simulation name',
                            type: 'string',
                            example: 'Simulation Name'
                        ),
                        new OA\Property(
                            property: 'version',
                            description: 'Simulation version',
                            type: 'string',
                            example: '1.0.0'
                        )
                    ]
                )
            )
        ),
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
                            example: 'missing_name',
                            summary: 'Name is required',
                            value: ['status' => 'Name is required']
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
                response: 201,
                description: 'Simulation created',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulation_created',
                            summary: 'Simulation created',
                            value: ['status' => 'Simulation created']
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Simulation updated',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulation_updated',
                            summary: 'Simulation updated',
                            value: ['status' => 'Simulation updated']
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
        if (null == $name = $request->request->get('name')) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Name is required'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $simRepo = $em->getRepository(Simulation::class);
        $isUpdate = (null == $sim = $simRepo->findOneBy(['watchdog' => $watchdog, 'name' => $name]));
        $sim ??= new Simulation();
        $sim
            ->setName($name)
            ->setVersion($request->request->get('version'))
            ->setWatchdog($watchdog);
        $em->persist($sim);
        $em->flush();
        return new JsonResponse(
            self::wrapPayloadForResponse(
                true,
                message: $isUpdate ? 'Simulation updated' : 'Simulation created'
            ),
            $isUpdate ? Response::HTTP_OK : Response::HTTP_CREATED
        );
    }

    private function getServerIdFromRequest(Request $request): Uuid
    {
        $serverId = $request->headers->get(strtolower('X-Server-Id'));
        if (!$serverId || !Uuid::isValid($serverId)) {
            throw new BadRequestHttpException('Missing or invalid header X-Server-Id. Must be a valid UUID');
        }
        return Uuid::fromString($serverId);
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
        summary: 'Delete a simulation for given watchdog server',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: [
                        'server_id', 'name'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'server_id',
                            description: 'Watchdog server ID',
                            type: 'string',
                            format: 'uuid',
                            example: '123e4567-e89b-12d3-a456-426614174000'
                        ),
                        new OA\Property(
                            property: 'name',
                            description: 'Simulation name',
                            type: 'string',
                            example: 'Simulation Name'
                        )
                    ]
                )
            )
        ),
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
                            example: 'missing_name',
                            summary: 'Name is required',
                            value: ['status' => 'Name is required']
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
                description: 'Simulation deleted',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'simulation_deleted',
                            summary: 'Simulation deleted',
                            value: ['status' => 'Simulation deleted']
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
        if (null == $name = $request->request->get('name')) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Name is required'),
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
        if (null === $sim = $simRepo->findOneBy(['watchdog' => $watchdog, 'name' => $name])) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Simulation not found'),
                Response::HTTP_NOT_FOUND
            );
        }
        $em->remove($sim);
        $em->flush();
        return new JsonResponse(
            self::wrapPayloadForResponse(true, message: 'Simulation deleted')
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
    public function notifyMonthSimulationFinished(Request $request): JsonResponse
    {
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $repo = $em->getRepository(Simulation::class);
        $watchdogServerId = Uuid::fromString($request->request->get('watchdog_server_id'));
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
            self::wrapPayloadForResponse(true, message: 'Month simulation finish has been noted')
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
        $em = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        if (null === $watchdog =
            $em->getRepository(Watchdog::class)->findOneBy(['serverId' => Watchdog::getInternalServerId()])
        ) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Internal watchdog server not found'),
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse(
            self::wrapPayloadForResponse(true, ['watchdog_token' => $watchdog->getToken()])
        );
    }
}
