<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\User;
use App\Domain\Common\MessageJsonResponse;
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
#[OA\Tag(name: 'User', description: 'Operations related to user management and authorization')]
class UserController extends BaseController
{
    #[Route(
        path: '/RequestSession',
        name: 'session_api_user_controller_requestsession',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Creates a new session for the desired country id',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['country_id', 'user_name'],
                    properties: [
                        new OA\Property(property: 'build_timestamp', type: 'string', default: '', nullable: true),
                        new OA\Property(property: 'country_id', type: 'integer', default: '1'),
                        new OA\Property(property: 'user_name', type: 'string', default: ''),
                        new OA\Property(property: 'country_password', type: 'string', default: '', nullable: true)
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session requested successfully',
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
                                                property: 'session_id',
                                                description: 'The session id generated for the user',
                                                type: 'integer'
                                            ),
                                            new OA\Property(property: 'api_access_token', type: 'string'),
                                            new OA\Property(property: 'api_refresh_token', type: 'string')
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
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'result',
                            summary: 'Missing or invalid session ID',
                            value: [
                                'success' => false,
                                'message' => 'Missing or invalid session ID'
                            ]
                        ),
                        new OA\Examples(
                            example: 'result2',
                            summary: 'Invalid country_id value. Must be an integer',
                            value: [
                                'success' => false,
                                'message' => 'Invalid country_id value. Must be an integer'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'result1',
                            summary: 'Could not authenticate you',
                            value: [
                                'success' => false,
                                'message' => 'Could not authenticate you. Your username and/or password could be '.
                                    'incorrect',
                            ]
                        ),
                        new OA\Examples(
                            example: 'result2',
                            summary: 'That password is incorrect',
                            value: [
                                'success' => false,
                                'message' => 'That password is incorrect'
                            ]
                        ),
                        new OA\Examples(
                            example: 'result3',
                            summary: 'You are not allowed to log on for that country',
                            value: [
                                'success' => false,
                                'message' => 'You are not allowed to log on for that country'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function requestSession(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        ServerManager $serverManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
        $sessionId = $this->getSessionIdFromRequest($request);
        // check if country_id get parameter is an int
        if (!ctype_digit($request->get('country_id'))) {
            return new MessageJsonResponse(message: 'Invalid country_id value. Must be an integer', status: 400);
        }

        try {
            $user = new User();
            $user->setGameSessionId($sessionId);
            $payload = $user->RequestSession(
                $request->get('build_timestamp', ''),
                (int)$request->get('country_id'),
                $request->get('user_name'),
                $request->get('country_password', '')
            );
            $user->setUserId($payload['session_id']);
            $user->setUsername($request->get('user_name'));
            $jsonResponse = $authenticationSuccessHandler->handleAuthenticationSuccess($user);
            $responseData = json_decode($jsonResponse->getContent());
            $payload['api_access_token'] = $responseData->token;
            $payload['api_refresh_token'] = $responseData->api_refresh_token;
            return new JsonResponse($payload);
        } catch (\Exception $e) {
            return new MessageJsonResponse(
                message: $e->getMessage().PHP_EOL.$e->getTraceAsString(),
                status: 500
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
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['api_refresh_token'],
                    properties: [
                        new OA\Property(
                            property: 'api_refresh_token',
                            description: 'The refresh token which was previously issued when requesting the session',
                            type: 'string'
                        )
                    ],
                    type: 'object'
                )
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
            return new JsonResponse($payload);
        } catch (\Exception $e) {
            return new MessageJsonResponse(
                message: $e->getMessage().PHP_EOL.$e->getTraceAsString(),
                status: 500
            );
        }
    }
}
