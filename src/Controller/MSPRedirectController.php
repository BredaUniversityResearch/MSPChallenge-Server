<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MSPRedirectController extends AbstractController
{
    #[Route(
        path: '/{slashes}{session}/{slashes2}api/{query}',
        name: 'api_session',
        requirements: ['session' => '\d+', 'query' => '.*', 'slashes' => '(\/+)?', 'slashes2' => '(\/+)?'],
        defaults: ['slashes' => '', 'slashes2' => ''],
        methods: ['GET', 'POST']
    )]
    public function __invoke(Request $request, int $session, $query): Response
    {
        return $this->redirect('/api/' . $query . '?session=' . $session, 308);
    }
}
