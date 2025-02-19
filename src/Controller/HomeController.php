<?php

namespace App\Controller;

use App\VersionsProvider;
use ServerManager\ServerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    /**
     * @throws \Exception
     */
    public function __invoke(
        string $projectDir,
        VersionsProvider $provider,
        ServerManager $serverManager
    ): Response {
        $downloads = scandir($projectDir.'/public/downloads/');
        $downloads = array_diff($downloads, ['.', '..']);
        return $this->render('home.html.twig', [
            'downloads' => $downloads,
            'version' => $provider->getVersion(),
            'serverAddress' => $serverManager->getTranslatedServerURL()
        ]);
    }
}
