<?php

namespace App\EventListener;

use App\Domain\API\v1\Router;
use App\Domain\Services\ConnectionManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckApiSessionIdListener
{
    private array $pathPatterns;

    public function __construct(
        array $pathPatterns,
        private readonly EntityManagerInterface $em,
        private readonly ConnectionManager $connectionManager
    ){
        $this->pathPatterns = $pathPatterns;
    }

    /**
     * @throws Exception
     */
    public function onKernelRequest(RequestEvent $event): void {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach ($this->pathPatterns as $pattern) {
            if (!preg_match('#' . $pattern . '#', $path)) {
                continue;
            }
            // check query parameter session
            $sessionId = $request->attributes->get('sessionId');
            if (!$sessionId || !is_numeric($sessionId)) {
                $event->setResponse(new JsonResponse(
                    Router::formatResponse(
                        false,
                        'Missing or invalid session ID',
                        null,
                        __CLASS__,
                        __FUNCTION__
                    ),
                    Response::HTTP_BAD_REQUEST
                ));
            }

            // since api platform uses the default entity manager, we need to set the connection to the msp_session_$sessionId
            $connection = $this->em->getConnection();
            if ($connection->isConnected()) {
                throw new \RuntimeException('Connection is already established.');
            }

            $otherConnection = $this->connectionManager->getCachedGameSessionDbConnection($sessionId);
            $connection->__construct(
                $otherConnection->getParams(),
                $otherConnection->getDriver(),
                $otherConnection->getConfiguration()
            );
            return;
        }
    }
}
