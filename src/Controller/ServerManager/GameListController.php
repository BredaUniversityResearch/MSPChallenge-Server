<?php

namespace App\Controller\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddFormType;
use App\Form\GameListEditFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
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
use App\Domain\Common\EntityEnums\GameStateValue;
use Symfony\Component\HttpFoundation\JsonResponse;
use Version\Exception\InvalidVersionString;
use App\Entity\ServerManager\Setting;
use App\Message\GameList\GameListCreationMessage;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Message\GameList\GameListArchiveMessage;

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
                self::wrapPayloadForResponse([], 'Please log in to the Server Manager for the first time.'),
                500
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

    #[Route('/manager/game/name/{sessionId}', name: 'manager_game_name', requirements: ['sessionId' => '\d+'])]
    public function gameSessionName(
        Request $request,
        EntityManagerInterface $entityManager,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $gameSession->setName($request->get('name'));
        $entityManager->flush();
        return new Response(null, 204);
    }

    #[Route('/manager/game/form', name: 'manager_game_form')]
    public function gameSessionForm(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus
    ): Response {
        $form = $this->createForm(GameListAddFormType::class, new GameList(), [
            'entity_manager' => $entityManager,
            'action' => $this->generateUrl('manager_game_form')
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            $entityManager->persist($gameSession);
            $entityManager->flush();
            $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
            return new Response($gameSession->getId(), 200);
        }
        return $this->render(
            'manager/GameList/game_form.html.twig',
            ['gameSessionForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/game/details/{sessionId}', name: 'manager_game_details', requirements: ['sessionId' => '\d+'])]
    public function gameSessionDetails(
        EntityManagerInterface $entityManager,
        int $sessionId = 1
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        return $this->render(
            'manager/GameList/game_details.html.twig',
            ['gameSession' => $gameSession]
        );
    }

    #[Route('/manager/game/{sessionId}/log/{type}', name: 'manager_game_log', requirements: ['sessionId' => '\d+'])]
    public function gameSessionLog(
        KernelInterface $kernel,
        Request $request,
        int $sessionId,
        string $type = 'excerpt'
    ): Response {
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
        if ($type == 'excerpt') {
            $logArray = array_slice($logArray, -5);
        }
        return $this->render('manager/GameList/game_log.html.twig', [
            'type' => $type,
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

    #[Route('/manager/game/{sessionId}/recreate', name: 'manager_game_recreate', requirements: ['sessionId' => '\d+'])]
    public function gameSessionRecreate(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
        return new Response($gameSession->getId(), 200);
    }

    #[Route('/manager/game/{sessionId}/archive', name: 'manager_game_archive', requirements: ['sessionId' => '\d+'])]
    public function gameSessionArchive(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $gameSession->setSessionState(new GameSessionStateValue('archived'));
        $entityManager->flush();
        $messageBus->dispatch(new GameListArchiveMessage($gameSession->getId()));
        return new Response(null, 204);
    }

    #[Route('/manager/game/{sessionId}/demo', name: 'manager_game_demo', requirements: ['sessionId' => '\d+'])]
    public function gameSessionDemo(
        EntityManagerInterface $entityManager,
        WatchdogCommunicator $watchdogCommunicator,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $gameSession->setDemoSession(($gameSession->getDemoSession() === 1) ? 0 : 1);
        $entityManager->flush();
        if ($gameSession->getDemoSession() == 1 && $gameSession->getGameState() != GameStateValue::PLAY) {
            (new GameController($this->projectDir))
            ->state($sessionId, GameStateValue::PLAY, $watchdogCommunicator, $symfonyToLegacyHelper);
        }
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

    #[Route('/manager/game/access/{sessionId}', name: 'manager_game_access', requirements: ['sessionId' => '\d+'])]
    public function gameSessionAccess(
        EntityManagerInterface $entityManager,
        int $sessionId = 1
    ): Response {
        // @todo
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        return $this->render(
            'manager/GameList/game_access.html.twig',
            ['gameSession' => $gameSession]
        );
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
}
