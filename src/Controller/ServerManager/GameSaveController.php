<?php

namespace App\Controller\ServerManager;

use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddBySaveLoadFormType;
use App\Form\GameSaveEditFormType;
use App\Form\GameSaveUploadFormType;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Domain\Common\GameSaveZipFileValidator;
use App\Domain\Services\SymfonyToLegacyHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\KernelInterface;

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
        requirements: ['saveVisibility' => '(active|archived)']
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
            ['action' => $this->generateUrl('manager_saves_details', ['saveId' => $saveId])]
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

    #[Route('/manager/saves/upload', name: 'manager_saves_upload')]
    public function gameSaveUpload(
        KernelInterface $kernel,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $form = $this->createForm(
            GameSaveUploadFormType::class,
            new GameSave(),
            ['action' => $this->generateUrl('manager_saves_upload')]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $saveZip = $form->get('saveZip')->getData();
            if (!$saveZip) {
                return new Response(null, 422);
            }
            $gameSaveZip = new GameSaveZipFileValidator($saveZip->getRealPath(), $kernel, $entityManager);
            if (!$gameSaveZip->isValid()) {
                foreach ($gameSaveZip->getErrors() as $errorMessage) {
                    $form->get('saveZip')->addError(new FormError($errorMessage));
                }
                return $this->render(
                    'manager/GameSave/gamesave_upload.html.twig',
                    ['gameSaveForm' => $form->createView()],
                    new Response(null, 422)
                );
            }
            $gameSave = $gameSaveZip->getGameSave();
            $entityManager->persist($gameSave);
            $entityManager->flush();
            try {
                $newFilename = sprintf($this->getParameter('app.server_manager_save_name'), $gameSave->getId());
                $saveZip->move($this->getParameter('app.server_manager_save_dir'), $newFilename);
            } catch (FileException $e) {
                $form->get('saveZip')->addError(new FormError('Could not save ZIP file on the server.'));
            }
        }
        return $this->render(
            'manager/GameSave/gamesave_upload.html.twig',
            ['gameSaveForm' => $form->createView()],
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
