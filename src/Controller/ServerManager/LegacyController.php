<?php

namespace App\Controller\ServerManager;

use App\Controller\MSPControllerBase;
use App\Domain\Helper\SymfonyToLegacyHelper;
use Exception;
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

    /**
     * @throws Exception
     */
    public function __invoke(Request $request, string $script): Response
    {
        $_SERVER['PHP_SELF'] = '/ServerManager/' . $script;
        $file = SymfonyToLegacyHelper::getInstance()->getProjectDir() . '/ServerManager/' . $script;
        if (!file_exists($file)) {
            throw new NotFoundHttpException();
        }
        ob_start();
        require($file);
        $content = ob_get_contents();
        ob_end_clean();

        return new Response($content);
    }

    public function notFound()
    {
        throw new NotFoundHttpException();
    }
}
