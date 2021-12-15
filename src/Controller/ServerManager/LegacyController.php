<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyController
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $_SERVER['DOCUMENT_ROOT'] = $this->projectDir;
    }

    public function __invoke(Request $request, string $script)
    {
        $_SERVER['PHP_SELF'] = '/ServerManager/' . $script;
        $file = $this->projectDir . '/ServerManager/' . $script;
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
