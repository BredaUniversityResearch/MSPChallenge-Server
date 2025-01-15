<?php

namespace App\Controller;

use App\Domain\Services\SymfonyToLegacyHelper;
use ServerManager\ServerManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyController extends LegacyControllerBase
{
    public function __construct(
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper,
        ServerManager $serverManager
    ) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $helper->getProjectDir());
        parent::__construct();
    }

    #[Route(
        path: '/{slashes}api/{query}',
        name: 'legacy_api_session',
        requirements: ['query' => '(?!doc$).*', 'slashes' => '(\/+)?'],
        defaults: ['slashes' => ''],
        methods: ['GET', 'POST'],
        priority: -1
    )]
    public function __invoke(Request $request, $query): JsonResponse
    {
        // for backwards compatibility
        $_REQUEST['query'] = $_GET['query'] = $query;
        $_REQUEST['session'] = $_GET['session'] = $this->getSessionIdFromRequest($request);
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

    #[Route(
        path: '/{anything}',
        name: 'legacy_not_found',
        requirements: ['anything' => '.*'],
        priority: -2
    )]
    public function notFound(Request $request): void
    {
        throw new NotFoundHttpException('URL not found: ' . $request->getRequestUri());
    }
}
