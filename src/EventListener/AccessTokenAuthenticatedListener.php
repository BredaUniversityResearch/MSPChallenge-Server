<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Doctrine\DBAL\Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AccessTokenAuthenticatedListener
{
    private ?Request $request;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ConnectionManager $connectionManager
    ) {
        $this->request = $this->requestStack->getCurrentRequest();
        if (is_null($this->request)) {
            $this->request = SymfonyToLegacyHelper::getInstance()->getRequest();
        }
    }


    /**
     * @param JWTAuthenticatedEvent $event
     *
     * @return void
     * @throws Exception
     */
    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $token = str_replace('Bearer ', '', $this->request->headers->get('Authorization'));
        $gameSessionId = $this->request->attributes->get('session');
        // temporary fallback while we continue migrating legacy code to Symfony...
        if (is_null($gameSessionId)) {
            throw new BadRequestHttpException('Missing or invalid session ID');
        }
        $connection = $this->connectionManager->getCachedGameSessionDbConnection($gameSessionId);
        $query = $connection->createQueryBuilder();
        $query->select('art.*')
            ->from('api_refresh_token', 'art')
            ->where('art.refresh_token = :rt')
            ->setParameter('rt', $token);
        $result = $connection->executeQuery($query->getSQL(), $query->getParameters())->fetchAllAssociative();
        if (!empty($result)) {
            // this is actually a refresh token, which we shouldn't allow to be used as an access token
            throw new InvalidTokenException();
        }
    }
}
