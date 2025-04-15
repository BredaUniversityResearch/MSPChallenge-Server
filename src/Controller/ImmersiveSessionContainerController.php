<?php

namespace App\Controller;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ImmersiveSessionService;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ImmersiveSession;
use App\Entity\ImmersiveSessionConnection;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    #[Route(
        path: '/create/{sessionId}',
        name: 'api_immersive_session_containers_create',
        methods: ['POST']
    )]
    public function create(Request $request, $sessionId): Response
    {
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $session = $em->getRepository(ImmersiveSession::class)->find($sessionId);
        if (!$session) {
            return new Response(
                'Immersive session not found: ' . $sessionId,
                Response::HTTP_NOT_FOUND
            );
        }
        try {
            $this->service->createImmersiveSessionContainer($session);
        } catch (Exception $e) {
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
    public function remove(Request $request, string $sessionId): Response
    {
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $session = $em->getRepository(ImmersiveSession::class)->find($sessionId);
        if (!$session) {
            return new Response(
                'Immersive session not found: ' . $sessionId,
                Response::HTTP_NOT_FOUND
            );
        }
        try {
            $this->service->removeImmersiveSessionContainer($session);
        } catch (Exception $e) {
            return new Response(
                'Error removing container: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response('Container removed successfully.');
    }
}
