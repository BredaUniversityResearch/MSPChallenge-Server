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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\KernelInterface;

class GameSaveController extends BaseController
{
    #[Route('/manager/gamesave', name: 'manager_gamesave')]
    public function index(): Response
    {
        return $this->render('manager/gamesave_page.html.twig');
    }

    #[Route(
        '/manager/gamesave/{saveVisibility}',
        name: 'manager_gamesave_list',
        requirements: ['saveVisibility' => '(active|archived)']
    )]
    public function gameSave(
        EntityManagerInterface $entityManager,
        string $saveVisibility
    ): Response {
        $gameSaves = $entityManager->getRepository(GameSave::class)->findBy(['saveVisibility' => $saveVisibility]);
        return $this->render('manager/GameSave/gamesave.html.twig', ['gameSaves' => $gameSaves]);
    }

    #[Route(
        '/manager/gamesave/{saveId}/download',
        name: 'manager_gamesave_download',
        requirements: ['saveId' => '\d+']
    )]
    public function gameSaveDownload(
        EntityManagerInterface $entityManager,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        if (is_null($gameSave)) {
            return new Response(null, 422);
        }
        $fileSystem = new Filesystem();
        
        $saveFileName = sprintf($this->getParameter('app.server_manager_save_name'), $saveId);
        $saveFilePath = $this->getParameter('app.server_manager_save_dir').$saveFileName;
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

    #[Route('/manager/gamesave/{saveId}/form', name: 'manager_gamesave_form', requirements: ['saveId' => '\d+'])]
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
                'action' => $this->generateUrl('manager_gamesave_form', ['saveId' => $saveId])
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

    #[Route('/manager/gamesave/{saveId}/details', name: 'manager_gamesave_details', requirements: ['saveId' => '\d+'])]
    public function gameSaveDetails(
        EntityManagerInterface $entityManager,
        Request $request,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $form = $this->createForm(
            GameSaveEditFormType::class,
            $gameSave,
            ['action' => $this->generateUrl('manager_gamesave_details', ['saveId' => $saveId])]
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

    #[Route('/manager/gamesave/upload', name: 'manager_gamesave_upload')]
    public function gameSaveUpload(
        KernelInterface $kernel,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $form = $this->createForm(
            GameSaveUploadFormType::class,
            new GameSave(),
            ['action' => $this->generateUrl('manager_gamesave_upload')]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $saveZip = $form->get('saveZip')->getData();
            $gameSaveZip = new GameSaveZipFileValidator($saveZip->getRealPath(), $kernel, $entityManager);
            if ($form->isValid() && self::isGameSaveZipValid($gameSaveZip, $form)) {
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
        }
        return $this->render(
            'manager/GameSave/gamesave_upload.html.twig',
            ['gameSaveForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/gamesave/{saveId}/archive', name: 'manager_gamesave_archive', requirements: ['saveId' => '\d+'])]
    public function gameSaveArchive(
        EntityManagerInterface $entityManager,
        int $saveId
    ): Response {
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('archived'));
        $entityManager->flush();
        return new Response(null, 204);
    }

    private static function isGameSaveZipValid(GameSaveZipFileValidator $gameSaveZip, FormInterface $form): bool
    {
        if (!$gameSaveZip->isValid()) {
            foreach ($gameSaveZip->getErrors() as $errorMessage) {
                $form->get('saveZip')->addError(new FormError($errorMessage));
            }
            return false;
        }
        return true;
    }
}
