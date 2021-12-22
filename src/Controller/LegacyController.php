<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyController
{
    public function __construct(
        string $projectDir,
        // below is required by legacy to be auto-wired
        \App\Domain\API\APIHelper $apiHelper
    ) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $projectDir);
    }

    public function __invoke(Request $request, $query, $session = -1, $debug = 0): JsonResponse
    {
        // for backwards compatibility
        $_REQUEST['query'] = $_GET['query'] = $query;
        $_REQUEST['session'] = $_GET['session'] = $session;
        $_REQUEST['debug'] = $_GET['debug'] = $debug;
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_POST = $request->request->all();
        foreach ($request->headers as $headerName => $headerValue) {
            $_SERVER['HTTP_' . strtoupper($headerName)] = $headerValue[0];
        }

        ob_start();
        require('legacy.php');
        $json = json_decode(ob_get_contents());
        ob_end_clean();

        return new JsonResponse($json, 200);
    }

    public function apiTest(Request $request): Response
    {
        ob_start();
        require('api_test/index.php');
        $content = ob_get_contents();
        ob_end_clean();

        return new Response($content);
    }

    public function notFound()
    {
        throw new NotFoundHttpException();
    }
}
