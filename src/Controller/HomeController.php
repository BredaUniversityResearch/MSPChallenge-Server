<?php

namespace App\Controller;

use App\VersionsProvider;
use ServerManager\ServerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route(
        path: '/',
        name: 'home'
    )]
    public function __invoke(string $projectDir): Response
    {
        if (!is_dir($projectDir.'/public/downloads/')) {
            mkdir($projectDir.'/public/downloads/');
        }
        $downloads = scandir($projectDir.'/public/downloads/');
        $downloads = array_diff($downloads, ['.', '..']);
        return $this->render('home.html.twig', [
            'downloads' => $downloads
        ]);
    }
}
