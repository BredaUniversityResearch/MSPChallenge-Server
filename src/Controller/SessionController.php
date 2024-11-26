<?php

namespace App\Controller;

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
        path: '/{slashes}{session}/{slashes2}api/{query}',
        name: 'api_session',
        requirements: ['session' => '\d+', 'query' => '.*', 'slashes' => '(\/+)?', 'slashes2' => '(\/+)?'],
        defaults: ['slashes' => '', 'slashes2' => ''],
        methods: ['GET', 'POST']
    )]
    public function __invoke(
        HttpKernelInterface $httpKernel,
        RouterInterface $router,
        Request $request,
        int $session,
        string $query
    ): Response {
        $routeInfo = $router->match('/api/'.$query);
        $subRequest = $request->duplicate(null, null, $routeInfo);
        $subRequest->attributes->set('sessionId', $session);
        return $httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
