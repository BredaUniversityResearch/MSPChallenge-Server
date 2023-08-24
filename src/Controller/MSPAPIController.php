<?php

namespace App\Controller;

use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class MSPAPIController extends AbstractController
{
    #[Route(
        '/{sessionId}/api/User/RequestSession/',
        name: 'server_API_user_requestsession',
        requirements: ['sessionId' => '\d+']
    )]
    public function requestSession(
        int $sessionId,
        Request $request,
        ConnectionManager $connectionManager,
        KernelInterface $kernel,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): Response {
        $user = new User();
        $user->setGameSessionId($sessionId);
        try {
            $payload = $user->RequestSession(
                $request->get('build_timestamp'),
                $request->get('country_id'),
                $request->get('user_name'),
                $request->get('country_password', '')
            );
            return new JsonResponse($this->wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse($this->wrapPayloadForResponse([], $e->getMessage().$e->getTraceAsString()));
        }
    }

    private function wrapPayloadForResponse(array $payload, ?string $message = null): array
    {
        return [
            'header_type' => '',
            'header_data' => '',
            'success' => is_null($message),
            'message' => $message,
            'payload' => $payload
        ];
    }
}
