<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Security\BearerTokenValidator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use ServerManager\ServerManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserController extends BaseController
{
    public function requestSession(
        int $sessionId,
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        ServerManager $serverManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
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

    public function requestToken(
        int $sessionId,
        Request $request,
        ConnectionManager $connectionManager,
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ): Response {
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
