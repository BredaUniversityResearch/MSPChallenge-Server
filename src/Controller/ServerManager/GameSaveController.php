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
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
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
        ContainerBagInterface $containerBag,
        int $id
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($id);
        if (is_null($gameSave)) {
            return new Response(null, 422);
        }
        $fileSystem = new FileSystem();
        $saveFileName = sprintf($containerBag->get('app.server_manager_save_name'), $id);
        $saveFilePath = $containerBag->get('app.server_manager_save_dir').$saveFileName;            
        if (!$fileSystem->exists($saveFilePath)) {
            return new Response(null, 422);
        }
        $response = new BinaryFileResponse($saveFilePath);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $saveFileName
        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }
}
