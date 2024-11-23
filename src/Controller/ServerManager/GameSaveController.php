<?php

namespace App\Controller\ServerManager;

use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddBySaveLoadFormType;
use App\Form\GameSaveEditFormType;
use App\Message\GameSave\GameSaveLoadMessage;
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

    #[Route('/manager/saves/{saveId}/download', name: 'manager_saves_download', requirements: ['saveId' => '\d+'])]
    public function gameSaveDownload(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        ContainerBagInterface $containerBag,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        if (is_null($gameSave)) {
            return new Response(null, 422);
        }
        $fileSystem = new FileSystem();
        $saveFileName = sprintf($containerBag->get('app.server_manager_save_name'), $saveId);
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

    #[Route('/manager/saves/{saveId}/form', name: 'manager_saves_form', requirements: ['saveId' => '\d+'])]
    public function gameSaveForm(
        EntityManagerInterface $entityManager,
        Request $request,
        MessageBusInterface $messageBus,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        int $saveId
    ): Response {
        $form = $this->createForm(
            GameListAddBySaveLoadFormType::class,
            new GameList(),
            [
                'save' => $saveId,
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_saves_form', ['saveId' => $saveId])
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            $entityManager->persist($gameSession);
            $entityManager->flush();
            $messageBus->dispatch(
                new GameSaveLoadMessage($gameSession->getId(), $gameSession->getGameSave()->getId())
            );
            return new Response($gameSession->getId(), 200);
        }
        return $this->render(
            'manager/GameSave/gamesave_form.html.twig',
            ['gameSaveForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/saves/{saveId}/details', name: 'manager_saves_details', requirements: ['saveId' => '\d+'])]
    public function gameSaveDetails(
        EntityManagerInterface $entityManager,
        Request $request,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $form = $this->createForm(
            GameSaveEditFormType::class,
            $gameSave,
            [
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_saves_details', ['saveId' => $saveId])
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSave = $form->getData();
            $entityManager->flush();
        }
        return $this->render(
            'manager/GameSave/gamesave_details.html.twig',
            [
                'gameSaveForm' => $form->createView(),
                'gameSave' => $gameSave
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/saves/{saveId}/archive', name: 'manager_save_archive', requirements: ['saveId' => '\d+'])]
    public function gameSaveArchive(
        EntityManagerInterface $entityManager,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('archived'));
        $entityManager->flush();
        return new Response(null, 204);
    }
}
