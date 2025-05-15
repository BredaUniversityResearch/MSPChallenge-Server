<?php

namespace App\Controller;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ImmersiveSessionService;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\SessionAPI\ImmersiveSession;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/immersive_session_containers')]
#[OA\Tag(
    name: 'Immersive session container',
    description: 'Operations related to immersive session container management.'
)]
class ImmersiveSessionContainerController extends BaseController
{
    public function __construct(
        // required by parent constructor
        string $projectDir,
        ConnectionManager $connectionManager,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        // required by this controller
        private readonly ImmersiveSessionService $service
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/create/{sessionId}',
        name: 'api_immersive_session_containers_create',
        methods: ['POST']
    )]
    #[OA\Parameter(
        name: 'sessionId',
        description: 'The ID of the immersive session',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function create(Request $request, int $sessionId): Response
    {
        $gameSessionId = $this->getSessionIdFromRequest($request);
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        /** @var ImmersiveSession|null $session */
        $session = $em->getRepository(ImmersiveSession::class)->find($sessionId);
        if (!$session) {
            return new Response(
                'Immersive session not found: ' . $sessionId,
                Response::HTTP_NOT_FOUND
            );
        }
        if ($session->getConnection() != null) {
            return new Response(
                'Immersive session already has a container: ' . $sessionId,
                Response::HTTP_ALREADY_REPORTED
            );
        }
        try {
            $this->service->createImmersiveSessionContainer($session, $gameSessionId);
        } catch (\Throwable $e) {
            return new Response(
                'Error starting container: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response('Container started successfully.');
    }

    /**
     * @throws Exception
     */
    #[Route(
        path: '/remove/{sessionId}',
        name: 'api_immersive_session_containers_remove',
        methods: ['POST']
    )]
    #[OA\Parameter(
        name: 'sessionId',
        description: 'The ID of the immersive session',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function remove(Request $request, int $sessionId): Response
    {
        $gameSessionId = $this->getSessionIdFromRequest($request);
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $session = $em->getRepository(ImmersiveSession::class)->find($sessionId);
        if (!$session) {
            return new Response(
                'Immersive session not found: ' . $sessionId,
                Response::HTTP_NOT_FOUND
            );
        }
        if ($session->getConnection() == null) {
            return new Response(
                'Immersive session does not have container: ' . $sessionId,
                Response::HTTP_ALREADY_REPORTED
            );
        }
        try {
            $this->service->removeImmersiveSessionContainer($session, $gameSessionId);
        } catch (\Throwable $e) {
            return new Response(
                'Error removing container: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response('Container removed successfully.');
    }
}
