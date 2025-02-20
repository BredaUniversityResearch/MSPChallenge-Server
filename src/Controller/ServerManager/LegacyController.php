<?php

namespace App\Controller\ServerManager;

use App\Controller\LegacyControllerBase;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use ServerManager\ServerManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    /**
     * @throws Exception
     */
    public function __invoke(Request $request, string $script): Response
    {
        $file = str_replace('_php', '.php', $script);
        $_SERVER['PHP_SELF'] = '/ServerManager/' . $script;
        $file = SymfonyToLegacyHelper::getInstance()->getProjectDir() . '/ServerManager/' . $file;
        if (!file_exists($file)) {
            throw new NotFoundHttpException($script.' not found');
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
