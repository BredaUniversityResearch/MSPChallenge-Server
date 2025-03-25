<?php

namespace App\Controller\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Services\ConnectionManager;
use App\Entity\Game;
use App\Entity\ServerManager\GameList;
use App\Entity\Watchdog;
use App\Form\GameListAddFormType;
use App\Form\GameListUserAccessFormType;
use App\Message\Watchdog\Message\GameStateChangedMessage;
use App\Repository\GameRepository;
use App\Repository\WatchdogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
use App\Domain\Common\GameListAndSaveSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Version\Exception\InvalidVersionString;
use App\Entity\ServerManager\Setting;
use App\Message\GameList\GameListCreationMessage;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameSave\GameSaveCreationMessage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(
    '/{manager}/gamelist',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameListController extends BaseController
{
    #[Route(
        '/{sessionState}',
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
                    false,
                    [
                        'clients_url' => $this->getParameter('app.clients_url'),
                        'server_version' => $provider->getVersion()
                    ],
                    $e->getMessage()
                ),
                403
            );
        } catch (InvalidVersionString $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 400);
        }
        $serverDesc = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        if (is_null($serverDesc)) {
            return new JsonResponse(
                self::wrapPayloadForResponse(false, message: 'Please log in to the Server Manager for the first time.'),
                500
            );
        }
        return $this->json(self::wrapPayloadForResponse(
            true,
            [
                'sessionslist' => $gameList,
                'server_description' => $serverDesc->getValue(),
                'clients_url' => $this->getParameter('app.clients_url'),
                'server_version' => $provider->getVersion(),
                'server_components_versions' => $provider->getComponentsVersions()
            ]
        ));
    }

    #[Route(
        '/{sessionId}/name',
        name: 'manager_gamelist_name',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
    public function gameSessionName(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $gameSession->setName($request->get('name'));
        $errors = $validator->validate($gameSession);
        if (count($errors) > 0) {
            return new Response(null, 422);
        }
        $entityManager->flush();
        return new Response(null, 204);
    }

    #[Route(
        '/{sessionId}/connect_watchdog/{watchdogId}',
        name: 'manager_gamelist_connect_watchdog',
        requirements: ['sessionId' => '\d+', 'watchdogId' => '\d+']
    )]
    public function connectToWatchdog(
        ConnectionManager $connectionManager,
        int $sessionId,
        int $watchdogId,
        MessageBusInterface $messageBus
    ): Response {
        try {
            $em = $connectionManager->getGameSessionEntityManager($sessionId);
            /** @var WatchdogRepository $watchdogRepo */
            $watchdogRepo = $em->getRepository(Watchdog::class);
            if (null === $watchdog = $watchdogRepo->find($watchdogId)) {
                throw new NotFoundHttpException('Watchdog not found');
            }
            $watchdog
                ->setDeletedAt(null)
                ->setStatus(WatchdogStatus::READY);
            $em->persist($watchdog);
            $em->flush();

            $gameListRepo = $connectionManager->getServerManagerEntityManager()
                ->getRepository(GameList::class);
            $gameList = $gameListRepo->find($sessionId);
            /** @var GameRepository $gameRepo */
            $gameRepo = $em->getRepository(Game::class);
            $game = $gameRepo->retrieve();

            $message = new GameStateChangedMessage();
            $message
                ->setGameSessionId($sessionId)
                ->setWatchdogId($watchdogId)
                ->setGameState(new GameStateValue($game->getGameState()))
                ->setMonth($gameList->getGameCurrentMonth());
            $messageBus->dispatch($message);
        } catch (Exception $e) {
            return new Response($e->getMessage(), 404);
        }
        return new Response(null, 204);
    }

    #[Route('/{sessionId}/form', name: 'manager_gamelist_form')]
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
                'action' => $this->generateUrl('manager_gamelist_form', ['sessionId' => $sessionId])
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
            ($sessionId == 0) ?
                'manager/GameList/gamelist_form.html.twig' : 'manager/GameList/gamelist_access.html.twig',
            [
                'gameSessionForm' => $form->createView(),
                'gameSessionCountries' => ($sessionId == 0) ? [] : $gameSession->getCountries()
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{sessionId}/details',
        name: 'manager_gamelist_details',
        requirements: ['sessionId' => '\d+']
    )]
    public function gameSessionDetails(
        EntityManagerInterface $entityManager,
        ConnectionManager $connectionManager,
        int $sessionId = 1
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $watchdogs = $connectionManager->getGameSessionEntityManager($sessionId)->getRepository(Watchdog::class)->
            findAll();
        return $this->render(
            'manager/GameList/gamelist_details.html.twig',
            ['gameSession' => $gameSession, 'watchdogs' => $watchdogs]
        );
    }

    #[Route(
        '/{sessionId}/log/{type}',
        name: 'manager_gamelist_log',
        requirements: ['sessionId' => '\d+']
    )]
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
        return $this->render('manager/GameList/gamelist_log.html.twig', [
            'type' => $type,
            'logToastBody' => $logArray
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{sessionId}/state/{state}',
        name: 'manager_gamelist_state',
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

    #[Route(
        '/{sessionId}/recreate',
        name: 'manager_gamelist_recreate',
        requirements: ['sessionId' => '\d+']
    )]
    public function gameSessionRecreate(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        $messageBus->dispatch(new GameListCreationMessage($gameSession->getId()));
        return new Response($gameSession->getId(), 200);
    }

    #[Route(
        '/{sessionId}/archive',
        name: 'manager_gamelist_archive',
        requirements: ['sessionId' => '\d+']
    )]
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

    #[Route('/{sessionId}/demo', name: 'manager_gamelist_demo', requirements: ['sessionId' => '\d+'])]
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
        '/{sessionId}/save/{type}',
        name: 'manager_gamelist_save',
        requirements: ['sessionId' => '\d+', 'type' => '\w+']
    )]
    public function gameSessionSave(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $sessionId,
        string $type = GameSaveTypeValue::FULL
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($sessionId);
        if ($gameSession->getSessionState() != GameSessionStateValue::HEALTHY
             || ($type != GameSaveTypeValue::FULL && $type != GameSaveTypeValue::LAYERS)) {
            return new Response(null, 422);
        }
        $serializer = new GameListAndSaveSerializer($entityManager);
        $gameSave = $serializer->createGameSaveFromData(
            $serializer->createDataFromGameList($gameSession)
        );
        if (!is_null($gameSession->getGameConfigVersion())) {
            $gameSave->setGameConfigFilesFilename(
                $gameSession->getGameConfigVersion()->getGameConfigFile()->getFilename()
            );
            $gameSave->setGameConfigVersionsRegion(
                $gameSession->getGameConfigVersion()->getRegion()
            );
        } elseif (!is_null($gameSession->getGameSave())) {
            $gameSave->setGameConfigFilesFilename(
                $gameSession->getGameSave()->getGameConfigFilesFilename()
            );
            $gameSave->setGameConfigVersionsRegion(
                $gameSession->getGameSave()->getGameConfigVersionsRegion()
            );
        }
        $gameSave->setSaveType(new GameSaveTypeValue($type));
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('active'));
        $entityManager->persist($gameSave);
        $entityManager->flush();
        $messageBus->dispatch(new GameSaveCreationMessage($sessionId, $gameSave->getId()));
        return new Response(null, 204);
    }

    #[Route(
        '/{sessionId}/export',
        name: 'manager_gamelist_export',
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
