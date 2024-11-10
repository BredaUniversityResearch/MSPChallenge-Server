<?php

namespace App\Controller\ServerManager;

use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Entity\ServerManager\GameSave;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class GameSaveController extends BaseController
{
    #[Route('/manager/saves', name: 'manager_saves')]
    public function index(): Response
    {
        return $this->render('manager/gamesave_page.html.twig');
    }

    #[Route(
        '/manager/saves/{saveVisibility}',
        name: 'manager_gamesave',
        requirements: ['saveVisibility' => '\w+']
    )]
    public function gameSave(
        EntityManagerInterface $entityManager,
        string $saveVisibility
    ): Response {
        $gameSaves = $entityManager->getRepository(GameSave::class)->findBy(['saveVisibility' => $saveVisibility]);
        return $this->render('manager/GameSave/gamesave.html.twig', ['gameSaves' => $gameSaves]);
    }

    #[Route('/manager/saves/{id}/download', name: 'manager_game_download', requirements: ['id' => '\d+'])]
    public function gameSessionDownload(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        int $id
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession) || $gameSession->getSessionState() != 'archived') {
            return new Response(null, 422);
        }
        $fileSystem = new FileSystem();
        $logPath = $kernel->getProjectDir() . "/ServerManager/session_archive/session_archive_{$id}.zip";
        if (!$fileSystem->exists($logPath)) {
            return new Response(null, 422);
        }
        $response = new BinaryFileResponse($logPath);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            "session_archive_{$id}.zip"
        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }
}
