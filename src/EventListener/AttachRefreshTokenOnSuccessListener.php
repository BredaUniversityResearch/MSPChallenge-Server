<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Lcobucci\JWT\Validation\Validator;
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
     * @throws \Doctrine\DBAL\Exception
     */
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();
        $parser = new Parser(new JoseEncoder());
        $pathArray = explode('/', ltrim($this->requestStack->getCurrentRequest()->getPathInfo(), '/'));
        $connection = $this->connectionManager->getCachedGameSessionDbConnection((int) $pathArray[0]);
        // extract refresh token from POST if it exists
        $currentRefreshToken = $this->requestStack->getCurrentRequest()->get('api_refresh_token');
        if (!is_null($currentRefreshToken)) {
            // if it exists, but it is not valid or we don't know about it, then don't continue
            try {
                /** @var UnencryptedToken $unencryptedToken */
                $unencryptedToken = $parser->parse($currentRefreshToken);
                $validator = new Validator();
                $validator->assert($unencryptedToken, new LooseValidAt(new FrozenClock(new \DateTimeImmutable())));
                $query = $connection->createQueryBuilder();
                $query->select('art.*')
                    ->from('api_refresh_token', 'art')
                    ->where('art.refresh_token = :rt')
                    ->setParameter('rt', $currentRefreshToken);
                $result = $connection->executeQuery($query->getSQL(), $query->getParameters())->fetchAllAssociative();
                if (empty($result)) {
                    throw new ConstraintViolation('Refresh token unknown to us');
                }
            } catch (Exception $e) {
                return;
            }
        }
        $query = $connection->createQueryBuilder();
        if (!is_null($currentRefreshToken)) {
            // attempt to delete refresh token from the db table
            $query->delete('api_refresh_token')
                ->where($query->expr()->eq(
                    'user_id',
                    $query->createPositionalParameter($user->getUserIdentifier())
                ));
            $connection->executeQuery($query->getSQL(), $query->getParameters());
        }
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
