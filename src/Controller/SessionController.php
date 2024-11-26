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
        // Clone the original attributes and add the session ID
        $attributes = $request->attributes->all();
        $attributes['sessionId'] = $session;
        // Merge the route information into the attributes
        $routeInfo = $router->match('/api/'.$query);
        $attributes = array_merge($attributes, $routeInfo);
        // Create a sub-request with the modified attributes, and leave the rest of the original request untouched
        $subRequest = $request->duplicate(attributes: $attributes);
        $subRequest->attributes->set('sessionId', $session);

        return $httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
