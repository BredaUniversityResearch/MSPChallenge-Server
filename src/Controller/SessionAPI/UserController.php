<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Security\BearerTokenValidator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use ServerManager\ServerManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/User')]
#[OA\Tag(name: 'User', description: 'Operations related to user management')]
class UserController extends BaseController
{
    #[Route(
        path: '/RequestSession',
        name: 'session_api_user_controller_requestsession',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Request a new session',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['build_timestamp', 'country_id', 'user_name', 'country_password'],
                    properties: [
                        new OA\Property(property: 'build_timestamp', type: 'string', default: ''),
                        new OA\Property(property: 'country_id', type: 'integer', default: '1'),
                        new OA\Property(property: 'user_name', type: 'string', default: ''),
                        new OA\Property(property: 'country_password', type: 'string', default: '', nullable: true)
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session requested successfully'),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function requestSession(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        ServerManager $serverManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
        $sessionId = $this->getSessionIdFromRequest($request);
        try {
            $user = new User();
            $user->setGameSessionId($sessionId);
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
            return new JsonResponse(self::wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse([], $e->getMessage().PHP_EOL.$e->getTraceAsString()),
                500
            );
        }
    }

    #[Route(
        path: '/RequestToken',
        name: 'session_api_user_controller_requesttoken',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Request a new token',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    // Define properties here
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token requested successfully'),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function requestToken(
        Request $request,
        ConnectionManager $connectionManager,
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
        $sessionId = $this->getSessionIdFromRequest($request);
        try {
            // if refresh does not exist, or is not valid, or we don't know about it, then don't continue
            $currentRefreshToken = $request->get('api_refresh_token');
            if (is_null($currentRefreshToken)) {
                throw new \Exception('Cannot continue without a refresh token');
            }
            $validator = new BearerTokenValidator($currentRefreshToken);
            if (!$validator->validate()) {
                throw new \Exception('Refresh token invalid');
            }
            $connection = $connectionManager->getCachedGameSessionDbConnection($sessionId);
            $query = $connection->createQueryBuilder();
            $query->select('art.*')
                ->from('api_refresh_token', 'art')
                ->where('art.refresh_token = :rt')
                ->setParameter('rt', $currentRefreshToken);
            $result = $connection->executeQuery($query->getSQL(), $query->getParameters())->fetchAllAssociative();
            if (empty($result)) {
                throw new \Exception('Refresh token unknown to us');
            }
            $decodedJwtToken = $validator->getClaims();
            if (!$decodedJwtToken->has('uid') || !$decodedJwtToken->has('username')) {
                throw new \Exception('Refresh token has no or the wrong payload');
            }
            // ok, ready to create new tokens!
            $user = new User();
            $user->setGameSessionId($sessionId);
            $user->setUserId($decodedJwtToken->get('uid'));
            $user->setUsername($decodedJwtToken->get('username'));
            $jsonResponse = $authenticationSuccessHandler->handleAuthenticationSuccess($user);
            $responseData = json_decode($jsonResponse->getContent());
            $payload['api_access_token'] = $responseData->token;
            $payload['api_refresh_token'] = $responseData->api_refresh_token;
            return new JsonResponse(self::wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse([], $e->getMessage().PHP_EOL.$e->getTraceAsString()),
                500
            );
        }
    }
}
