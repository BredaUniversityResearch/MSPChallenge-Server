<?php

namespace App\Controller;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        defaults: ['slashes' => '', 'slashes2' => '', 'slashes3' => ''],
        methods: ['GET', 'POST']
    )]
    public function __invoke(
        HttpKernelInterface $httpKernel,
        RouterInterface $router,
        ConnectionManager $connectionManager,
        Request $request,
        int $session,
        string $query
    ): Response {
        try {
            $connectionManager->getCachedGameSessionDbConnection($session)->connect();
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Session database does not exist.');
        }
        if (null === $gameList = $connectionManager->getServerManagerEntityManager()->find(
            GameList::class,
            $session
        )) {
            throw $this->createNotFoundException('Session not found.');
        }
        if ($gameList->getSessionState() != GameSessionStateValue::HEALTHY) {
            throw $this->createNotFoundException('Unable to use session.');
        }

        // Merge the route information into the attributes
        $routeInfo = $router->match('/api/'.$query);
        $attributes = array_merge($request->attributes->all(), $routeInfo);
        // Create a sub-request with the modified attributes, and leave the rest of the original request untouched
        $subRequest = $request->duplicate(attributes: $attributes);
        return $httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
