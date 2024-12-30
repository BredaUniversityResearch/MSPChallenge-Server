<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\EntityManagerInterface;

class GameConfigVersionController extends BaseController
{
    #[Route('/manager/gameconfig', name: 'manager_gameconfig')]
    public function index(): Response
    {
        return $this->render('manager/gameconfigversion_page.html.twig');
    }

    #[Route(
        '/manager/gameconfig/{visibility}',
        name: 'manager_gameconfig_list',
        requirements: ['visibility' => '(active|archived)']
    )]
    public function gameConfigVersion(
        EntityManagerInterface $entityManager,
        string $visibility
    ): Response {
        $gameConfigVersions = $entityManager->getRepository(GameConfigVersion::class)
            ->findBy(['visibility' => $visibility]);
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion.html.twig',
            ['configslist' => $gameConfigVersions]
        );
    }

    #[Route(
        '/manager/gameconfig/{configId}/details',
        name: 'manager_gameconfig_details',
        requirements: ['configId' => '\d+']
    )]
    public function gameConfigVersionDetails(
        EntityManagerInterface $entityManager,
        int $configId
    ): Response {
        $gameConfigVersion = $entityManager->getRepository(GameConfigVersion::class)->find($configId);
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion_details.html.twig',
            ['gameConfigVersion' => $gameConfigVersion]
        );
    }
}
