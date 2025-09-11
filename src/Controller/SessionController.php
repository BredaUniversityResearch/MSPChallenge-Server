<?php

namespace App\Controller;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class SessionController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route(
        path: '/{slashes}{session}/{slashes2}api/{slashes3}{query}',
        name: 'api_session',
        requirements: [
            'session' => '\d+', // the session id
            'query' => '.*', // everything after /api/,
            'slashes' => '\/*',
            'slashes2' => '\/*',
            'slashes3' => '\/*'
        ],
        defaults: ['slashes' => '', 'slashes2' => '', 'slashes3' => '']
    )]
    public function __invoke(
        HttpKernelInterface $httpKernel,
        RouterInterface $router,
        ConnectionManager $connectionManager,
        Request $request,
        EntityManagerInterface $em,
        int $session,
        string $query
    ): Response {
        try {
            $connectionManager->getCachedGameSessionDbConnection($session)->connect();
        } catch (ConnectionException $e) {
            if ($e->getCode() == 1049) { // MySQL Unknown database
                throw new HttpException(410, 'Session is non-existing');
            }
            throw $e;
        }
        if (null === $gameList = $connectionManager->getServerManagerEntityManager()->find(
            GameList::class,
            $session
        )) {
            throw new HttpException(410, 'Session is non-existing');
        }
        if ($gameList->getSessionState() != GameSessionStateValue::HEALTHY) {
            throw new HttpException(503, 'Session cannot be used at the moment');
        }

        // since api platform uses the default entity manager,
        //  we need to set the connection to the msp_session_$sessionId
        $connection = $em->getConnection();
        if ($connection->isConnected()) {
            throw new \RuntimeException('Connection is already established.');
        }

        $otherConnection = $connectionManager->getCachedGameSessionDbConnection($session);
        $connection->__construct(
            $otherConnection->getParams(),
            $otherConnection->getDriver(),
            $otherConnection->getConfiguration()
        );

        // Merge the route information into the attributes
        $routeInfo = $router->match('/api/'.$query);
        $attributes = array_merge($request->attributes->all(), $routeInfo);
        // Create a sub-request with the modified attributes, and leave the rest of the original request untouched
        $subRequest = $request->duplicate(attributes: $attributes);
        return $httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
