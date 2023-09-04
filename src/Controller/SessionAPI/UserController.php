<?php

namespace App\Controller\SessionAPI;

use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Validator;
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
            return $this->requestToken(
                $sessionId,
                $request,
                $connectionManager,
                $tokenStorageInterface,
                $jwtManager,
                $authenticationSuccessHandler
            );
        }
        return new Response();
    }

    #[Route(
        '/{sessionId}/api/User/RequestSession/',
        name: 'session_api_user_request_session',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
    public function requestSession(
        int $sessionId,
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
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
            return new JsonResponse(BaseController::wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse(
                BaseController::wrapPayloadForResponse([], $e->getMessage().PHP_EOL.$e->getTraceAsString()),
                500
            );
        }
    }

    #[Route(
        '/{sessionId}/api/User/RequestToken/',
        name: 'session_api_user_request_token',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
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
            $connection = $connectionManager->getCachedGameSessionDbConnection($sessionId);
            $parser = new Parser(new JoseEncoder());
            // this might throw an exception because refresh token is not a valid JWT
            $unencryptedToken = $parser->parse($currentRefreshToken);
            $validator = new Validator();
            // this might throw an exception because refresh token is no longer valid
            $validator->assert($unencryptedToken, new LooseValidAt(new FrozenClock(new \DateTimeImmutable())));
            $query = $connection->createQueryBuilder();
            $query->select('art.*')
                ->from('api_refresh_token', 'art')
                ->where('art.refresh_token = :rt')
                ->setParameter('rt', $currentRefreshToken);
            $result = $connection->executeQuery($query->getSQL(), $query->getParameters())->fetchAllAssociative();
            if (empty($result)) {
                throw new \Exception('Refresh token unknown to us');
            }
            $decodedJwtToken = $unencryptedToken->claims();
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
            return new JsonResponse(BaseController::wrapPayloadForResponse($payload));
        } catch (\Exception $e) {
            return new JsonResponse(
                BaseController::wrapPayloadForResponse([], $e->getMessage().PHP_EOL.$e->getTraceAsString()),
                500
            );
        }
    }
}
