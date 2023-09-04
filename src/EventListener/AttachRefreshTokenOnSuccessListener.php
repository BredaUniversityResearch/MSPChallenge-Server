<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use Doctrine\DBAL\Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AttachRefreshTokenOnSuccessListener
{

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ConnectionManager $connectionManager,
        private readonly JWTTokenManagerInterface $JWTManager
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();
        $pathArray = explode('/', ltrim($this->requestStack->getCurrentRequest()->getPathInfo(), '/'));
        $connection = $this->connectionManager->getCachedGameSessionDbConnection((int) $pathArray[0]);
        $query = $connection->createQueryBuilder();
        // delete user's refresh token from the db table (won't exist upon first login using RequestSession)
        $query->delete('api_refresh_token')
            ->where($query->expr()->eq(
                'user_id',
                $query->createPositionalParameter($user->getUserIdentifier())
            ))
            ->orWhere($query->expr()->lte(
                'valid',
                $query->createPositionalParameter(date("Y-m-d H:i:s"))
            ));
        $connection->executeQuery($query->getSQL(), $query->getParameters());
        // create new refresh token and add to the db table
        $expiration = new \DateTime('+1 day');
        $data['exp'] = $expiration->getTimestamp();
        $newRefreshToken = $this->JWTManager->createFromPayload($user, $data);
        $query = $connection->createQueryBuilder();
        $query->insert('api_refresh_token')
            ->values([
                'refresh_token' => '?',
                'user_id' => '?',
                'valid' => '?'
            ])
            ->setParameters([
                $newRefreshToken,
                $user->getUserIdentifier(),
                $expiration->format('Y-m-d H:i:s')
            ]);
        $connection->executeQuery($query->getSQL(), $query->getParameters());
        // add new refresh token to the response data
        $data['api_refresh_token'] = $newRefreshToken;
        $event->setData($data);
    }
}
