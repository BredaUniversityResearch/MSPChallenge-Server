<?php

namespace App\Controller\SessionAPI;

use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserController extends AbstractController
{

    #[Route(
        '/{sessionId}/api/User/{method}',
        name: 'session_api_user_controller',
        requirements: ['sessionId' => '\d+', 'method' => '\w+']
    )]
    public function __invoke(
        int $sessionId,
        string $method,
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        ConnectionManager $connectionManager,
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
        if (strtolower($method) == 'requestsession') {
            return $this->requestSession($sessionId, $request, $symfonyToLegacyHelper, $authenticationSuccessHandler);
        }
        if (strtolower($method) == 'requesttoken') {
            return $this->requestToken($tokenStorageInterface, $jwtManager, $authenticationSuccessHandler);
        }
        return new Response();
    }

    #[Route(
        '/{sessionId}/api/User/RequestSession/',
        name: 'session_api_user_request_session',
        requirements: ['sessionId' => '\d+']
    )]
    public function requestSession(
        int $sessionId,
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        AuthenticationSuccessHandler $authenticationSuccessHandler
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
            $user->setUserId($payload['session_id']);
            $user->setUsername($request->get('user_name'));
            $jsonResponse = $authenticationSuccessHandler->handleAuthenticationSuccess($user);
            $responseData = json_decode($jsonResponse->getContent());
            $payload['api_access_token'] = $responseData->token;
            $payload['api_refresh_token'] = $responseData->api_refresh_token;
            return new JsonResponse($this->wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse($this->wrapPayloadForResponse([], $e->getMessage().$e->getTraceAsString()));
        }
    }

    #[Route(
        '/{sessionId}/api/User/RequestToken/',
        name: 'session_api_user_request_token',
        requirements: ['sessionId' => '\d+']
    )]
    public function requestToken(
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
        try {
            $decodedJwtToken = $jwtManager->decode($tokenStorageInterface->getToken());
            $user = new User();
            $user->setUserId($decodedJwtToken['uid']);
            $user->setUsername($decodedJwtToken['username']);
            $jsonResponse = $authenticationSuccessHandler->handleAuthenticationSuccess($user);
            $responseData = json_decode($jsonResponse->getContent());
            $payload['api_access_token'] = $responseData->token;
            $payload['api_refresh_token'] = $responseData->api_refresh_token;
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
