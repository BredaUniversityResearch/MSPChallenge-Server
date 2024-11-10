<?php

namespace App\Controller\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddFormType;
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
use App\Domain\API\v1\Plan;
use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use Symfony\Component\HttpFoundation\JsonResponse;
use Version\Exception\InvalidVersionString;
use App\Entity\ServerManager\Setting;
use App\Message\GameList\GameListCreationMessage;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Form\GameListUserAccessFormType;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameSave\GameSaveCreationMessage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameListController extends BaseController
{
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
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->gameClientJson($entityManager, $provider, $request, $gameList);
        }
        return $this->render('manager/GameList/gamelist.html.twig', ['sessionslist' => $gameList]);
    }

    private function gameClientJson(
        EntityManagerInterface $entityManager,
        VersionsProvider $provider,
        Request $request,
        array $gameList
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

    #[Route(
        '/manager/game/name/{sessionId}',
        name: 'manager_game_name',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
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

    #[Route('/manager/game/form/{sessionId}', name: 'manager_game_form')]
    public function gameSessionForm(
        EntityManagerInterface $entityManager,
        Request $request,
        MessageBusInterface $messageBus,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        int $sessionId = 0
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $form = $this->createForm(
            ($sessionId == 0) ? GameListAddFormType::class : GameListUserAccessFormType::class,
            ($sessionId == 0) ? new GameList() : $gameSession,
            [
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_game_form', ['sessionId' => $sessionId])
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            if ($sessionId == 0) {
                $entityManager->persist($gameSession);
                $entityManager->flush();
                $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
                return new Response($gameSession->getId(), 200);
            }
            $entityManager->flush();
            return new Response('0', 200);
        }
        return $this->render(
            ($sessionId == 0) ? 'manager/GameList/game_form.html.twig' : 'manager/GameList/game_access.html.twig',
            [
                'gameSessionForm' => $form->createView(),
                'gameSessionCountries' => ($sessionId == 0) ? [] : $gameSession->getCountries()
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/game/{sessionId}/details', name: 'manager_game_details', requirements: ['sessionId' => '\d+'])]
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
        int $sessionId = 1,
        string $type = 'complete'
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
        KernelInterface $kernel,
        WatchdogCommunicator $watchdogCommunicator,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): Response {
        (new GameController($kernel->getProjectDir()))
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
        KernelInterface $kernel,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $gameSession->setDemoSession(($gameSession->getDemoSession() === 1) ? 0 : 1);
        $entityManager->flush();
        if ($gameSession->getDemoSession() == 1 && $gameSession->getGameState() != GameStateValue::PLAY) {
            (new GameController($kernel->getProjectDir()))
            ->state($sessionId, GameStateValue::PLAY, $watchdogCommunicator, $symfonyToLegacyHelper);
        }
        return new Response(null, 204);
    }

    #[Route(
        '/manager/game/{sessionId}/save/{type}',
        name: 'manager_game_save',
        requirements: ['sessionId' => '\d+', 'type' => '\w+']
    )]
    public function gameSessionSave(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId,
        string $type = GameSaveTypeValue::FULL
    ): Response {
        $gameListRepo = $entityManager->getRepository(GameList::class);
        $gameSaveRepo = $entityManager->getRepository(GameSave::class);
        $gameSession = $gameListRepo->find($sessionId);
        if ($gameSession->getSessionState() != GameSessionStateValue::HEALTHY
             || ($type != GameSaveTypeValue::FULL && $type != GameSaveTypeValue::LAYERS)) {
            return new Response(null, 422);
        }
        $gameSave = $gameSaveRepo->createGameSaveFromData(
            $gameListRepo->createDataFromGameList($gameSession)
        );
        $gameSave->setSaveType(new GameSaveTypeValue($type));
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('active'));
        $entityManager->persist($gameSave);
        $entityManager->flush();
        $messageBus->dispatch(new GameSaveCreationMessage($sessionId, $gameSave->getId()));
        return new Response(null, 204);
    }

    #[Route(
        '/manager/game/{sessionId}/export',
        name: 'manager_game_export',
        requirements: ['sessionId' => '\d+']
    )]
    public function gameSessionExport(
        EntityManagerInterface $entityManager,
        int $sessionId,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        if ($gameSession->getSessionState() != GameSessionStateValue::HEALTHY) {
            return new Response(null, 422);
        }
        $response = new StreamedResponse(function () use ($gameSession) {
            $plan = new Plan();
            $exportedPlans = $plan->ExportPlansToJson($gameSession->getId());
            $gameConfigComplete = $gameSession->getGameConfigVersion()->getGameConfigComplete();
            // force an object from meta layer type array
            $gameConfigComplete['datamodel']['meta'] = array_map(function ($el) {
                $el['layer_type'] = (object) $el['layer_type'];
                return $el;
            }, $gameConfigComplete['datamodel']['meta']);
            $gameConfigComplete['datamodel']['plans'] = $exportedPlans;
            echo json_encode($gameConfigComplete, JSON_PRETTY_PRINT);
        });
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($gameSession->getGameConfigVersion()->getFilePath(), '.json').'_With_Exported_Plans.json'
        ));
        return $response;
    }
}
