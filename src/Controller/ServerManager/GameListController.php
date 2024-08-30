<?php

namespace App\Controller\ServerManager;

use App\Domain\API\v1\Game;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddFormType;
use App\Form\GameListEditFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\VersionsProvider;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\IncompatibleClientException;
use App\Controller\BaseController;
use App\Controller\SessionAPI\GameController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Version\Exception\InvalidVersionString;
use App\Entity\ServerManager\Setting;
use App\Message\GameList\GameListCreationMessage;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Domain\Communicator\WatchdogCommunicator;

class GameListController extends BaseController
{

    public function __construct(
        private readonly string $projectDir
    ) {
    }

    #[Route('/manager', name: 'manager')]
    public function index(): Response
    {
        return $this->render('manager/gamelist_page.html.twig');
    }

    #[Route(
        '/manager/gamelist/{sessionState}',
        name: 'manager_gamelist',
        requirements: ['sessionState' => '\w+']
    )]
    public function gameList(
        EntityManagerInterface $entityManager,
        VersionsProvider $provider,
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        string $sessionState = 'public'
    ): Response {
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->gameListJson($entityManager, $provider, $request, $sessionState);
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        return $this->render('manager/GameList/gamelist.html.twig', ['sessionslist' => $gameList]);
    }

    private function gameListJson(
        EntityManagerInterface $entityManager,
        VersionsProvider $provider,
        Request $request,
        string $sessionState
    ): Response {
        try {
            $provider->checkCompatibleClient($request->headers->get('Msp-Client-Version'));
        } catch (IncompatibleClientException $e) {
            return new JsonResponse(
                self::wrapPayloadForResponse(
                    [
                        'clients_url' => $this->getParameter('app.clients_url'),
                        'server_version' => $provider->getVersion()
                    ],
                    $e->getMessage()
                ),
                403
            );
        } catch (InvalidVersionString $e) {
            return new JsonResponse(self::wrapPayloadForResponse([], $e->getMessage()), 400);
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        $serverDesc = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        if (is_null($serverDesc)) {
            return new JsonResponse(
                self::wrapPayloadForResponse([], 'This server is not configured yet. Please log in to the Server Manager first.', 500)
            );
        }
        return $this->json(self::wrapPayloadForResponse([
                'sessionslist' => $gameList,
                'server_description' => $serverDesc->getValue(),
                'clients_url' => $this->getParameter('app.clients_url'),
                'server_version' => $provider->getVersion(),
                'server_components_versions' => $provider->getComponentsVersions()
            ]));
    }

    #[Route('/manager/game/{sessionId}', name: 'manager_game_form', requirements: ['sessionId' => '\d+'])]
    public function gameSessionForm(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId = 0
    ): Response {
        if ($sessionId > 0) {
            $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
            $form = $this->createForm(GameListEditFormType::class, $gameSession, [
                'action' => $this->generateUrl('manager_game_form', ['id' => $sessionId])
            ]);
        } else {
            $form = $this->createForm(GameListAddFormType::class, new GameList(), [
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_game_form')
            ]);
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            $entityManager->persist($gameSession);
            $entityManager->flush();
            if ($sessionId == 0) {
                $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
                return new Response($gameSession->getId(), 200);
            }
            return new Response(null, 204);
        }
        return $this->render(
            $sessionId == 0 ? 'manager/GameList/new_game.html.twig': 'manager/GameList/existing_game.html.twig',
            ['gameSessionForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/game/{sessionId}/log', name: 'manager_game_log', requirements: ['sessionId' => '\d+'])]
    public function gameSessionLog(KernelInterface $kernel, Request $request, int $sessionId): Response
    {
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->redirectToRoute('manager');
        }
        $fileSystem = new FileSystem();
        $logPath = $kernel->getProjectDir() . "/ServerManager/log/log_session_{$sessionId}.log";
        if (!$fileSystem->exists($logPath)) {
            return new Response(null, 422);
        }
        $rawLogContents = file_get_contents($logPath);
        $rawLogContents = preg_replace(
            ['/\[[0-9\-\s:+.T]+\]/', '/game\_session\./', '/\{["\w:,]+\} \[\]/', '/\[\]/'],
            [ '', '', '', ''],
            $rawLogContents
        );
        $logArray = explode('<br />', nl2br(trim($rawLogContents)));
        $logArray = array_slice($logArray, -5);
        return $this->render('manager/GameList/game_log.html.twig', [
            'logToastBody' => $logArray
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/manager/game/{sessionId}/state/{state}',
        name: 'manager_game_state',
        requirements: ['sessionId' => '\d+', 'state' => '\w+']
    )]
    public function gameSessionState(
        int $sessionId,
        string $state,
        WatchdogCommunicator $watchdogCommunicator,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): Response {
        (new GameController($this->projectDir))
            ->state($sessionId, $state, $watchdogCommunicator, $symfonyToLegacyHelper);
        return new Response(null, 204);
    }

    #[Route('/manager/game/{id}/recreate', name: 'manager_game_recreate', requirements: ['id' => '\d+'])]
    public function gameSessionRecreate(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession)) {
            return new Response(null, 422);
        }
        if (!is_null($gameSession->getGameConfigVersion())) {
            $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
            return new Response($gameSession->getId(), 200);
        }
        if (!is_null($gameSession->getGameSave())) {
            $messageBus->dispatch(new GameSaveLoadMessage($gameSession->getId(), $gameSession->getGameSave()->getId()));
            return new Response($gameSession->getId(), 200);
        }
        return new Response(null, 422);
    }

    #[Route('/manager/game/{id}/archive', name: 'manager_game_archive', requirements: ['id' => '\d+'])]
    public function gameSessionArchive(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession) || $gameSession->getSessionState() == 'archived') {
            return new Response(null, 422);
        }
        $gameSession->setSessionState(new GameSessionStateValue('archived'));
        $entityManager->persist($gameSession);
        $entityManager->flush();
        //$messageBus->dispatch(new GameListSessionArchive($gameSession->getId()));
        return new Response(null, 204);
    }

    #[Route(
        '/manager/game/{id}/save/{type}',
        name: 'manager_game_save',
        requirements: ['id' => '\d+', 'type' => '\w+']
    )]
    public function gameSessionSave(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id,
        string $type = 'full'
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession) || $gameSession->getSessionState() != 'healthy' ||
            ($type != 'full' && $type != 'layers')
        ) {
            return new Response(null, 422);
        }
        $gameSave = (new GameSave)->createFromGameList($gameSession);
        $entityManager->persist($gameSave);
        $entityManager->flush();
        if ($type == 'full') {
            //$messageBus->dispatch(new GameSaveFullSession($gameSave->getId()));
        }
        if ($type == 'layers') {
            //$messageBus->dispatch(new GameSaveLayersSession($gameSave->getId()));
        }
        return new Response(null, 204);
    }

    #[Route('/manager/game/{id}/export', name: 'manager_game_export', requirements: ['id' => '\d+'])]
    public function gameSessionExport(EntityManagerInterface $entityManager, int $id): Response
    {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession) || $gameSession->getSessionState() != 'healthy') {
            return new Response(null, 422);
        }
        // probably best to do this through the GameConfigFile class
        // should be immediately returned as a file download
        return new Response(null, 204);
    }

    #[Route('/manager/game/{id}/download', name: 'manager_game_download', requirements: ['id' => '\d+'])]
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

    #[Route('/manager/game/{id}/demo', name: 'manager_game_demo', requirements: ['id' => '\d+'])]
    public function gameSessionDemo(EntityManagerInterface $entityManager, int $id): Response
    {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession)) {
            return new Response(null, 422);
        }
        $gameSession->setDemoSession(($gameSession->getDemoSession() === 1) ? 0 : 1);
        $entityManager->persist($gameSession);
        $entityManager->flush();
        return new Response(null, 204);
    }
}
