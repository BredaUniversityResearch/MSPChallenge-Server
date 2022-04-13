<?php

namespace App\Controller;

use App\Domain\Helper\SymfonyToLegacyHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyController extends MSPControllerBase
{
    public function __construct(
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper
    ) {
        set_include_path(get_include_path() . PATH_SEPARATOR . SymfonyToLegacyHelper::getInstance()->getProjectDir());
        parent::__construct();
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

    public function apidoc(): void
    {
        require('Documentation/index.html');
        exit;
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
