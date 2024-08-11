<?php

namespace App\Controller\ServerManager;

<<<<<<< HEAD
use App\Domain\API\v1\Game;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddFormType;
use App\Form\GameListEditFormType;
use App\Messages\GameListSessionCreate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class GameListController extends AbstractController
{

    #[Route('/manager', name: 'manager')]
    public function index(): Response
    {
        return $this->render('manager/gamelist_page.html.twig');
    }

    #[Route('/manager/gamelist/{sessionState}', name: 'manager_gamelist', requirements: ['sessionState' => '\w+'])]
    public function gameList(
        EntityManagerInterface $entityManager,
        Request $request,
        string $sessionState = 'public'
    ): Response {
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->redirectToRoute('manager');
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        return $this->render('manager/GameList/gamelist.html.twig', [
            'gameList' => $gameList
        ]);
    }

    #[Route('/manager/game/{id}', name: 'manager_game_form', requirements: ['id' => '\d+'])]
    public function gameSessionForm(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id = 0
    ): Response {
        if ($id > 0) {
            $gameSession = $entityManager->getRepository(GameList::class)->find($id);
            $form = $this->createForm(GameListEditFormType::class, $gameSession, [
                'action' => $this->generateUrl('manager_game_form', ['id' => $id])
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
            if ($id == 0) {
                if (!is_null($gameSession->getGameConfigVersion())) {
                    $messageBus->dispatch(new GameListSessionCreate($gameSession->getId()));
                    return new Response($gameSession->getId(), 200);
                }
                if (!is_null($gameSession->getGameSave())) {
                    //$messageBus->dispatch(new GameListSessionLoading($gameSession->getId()));
                    return new Response($gameSession->getId(), 200);
                }
            }
            return new Response(null, 204);
        }
        return $this->render(
            $id == 0 ? 'manager/GameList/new_game.html.twig': 'manager/GameList/existing_game.html.twig',
            ['gameSessionForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/manager/game/{id}/log', name: 'manager_game_log', requirements: ['id' => '\d+'])]
    public function gameSessionLog(KernelInterface $kernel, Request $request, int $id): Response
    {
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->redirectToRoute('manager');
        }
        $fileSystem = new FileSystem();
        $logPath = $kernel->getProjectDir() . "/ServerManager/log/log_session_{$id}.log";
        if (!$fileSystem->exists($logPath)) {
            return new Response(null, 422);
        }
        $logArray = explode('<br />', nl2br(trim(file_get_contents($logPath))));
        $logArray = array_slice($logArray, -5);
        return $this->render('manager/GameList/game_log.html.twig', [
            'logToastBody' => $logArray
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/manager/game/{id}/state/{state}',
        name: 'manager_game_state',
        requirements: ['id' => '\d+', 'state' => '\w+']
    )]
    public function gameSessionState(EntityManagerInterface $entityManager, int $id, string $state): Response
    {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession) ||
            $gameSession->getGameState() == $state ||
            !in_array($state, GameStateValue::getConstants())
        ) {
            return new Response(null, 422);
        }
        // note: not persisting GameList object, because the websocket service takes care of that
        $game = new Game();
        $game->setGameSessionId($gameSession->getId());
        $game->State($state);
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
            $messageBus->dispatch(new GameListSessionCreate($gameSession->getId()));
            return new Response($gameSession->getId(), 200);
        }
        if (!is_null($gameSession->getGameSave())) {
            //$messageBus->dispatch(new GameListSessionLoad($gameSession->getId()));
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
=======
use App\Controller\BaseController;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\Setting;
use App\IncompatibleClientException;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Version\Exception\InvalidVersionString;

class GameListController extends BaseController
{
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
        return is_null($request->headers->get('Turbo-Frame')) ?
            $this->json(self::wrapPayloadForResponse([
                'sessionslist' => $gameList,
                'server_description' => $serverDesc->getValue(),
                'clients_url' => $this->getParameter('app.clients_url'),
                'server_version' => $provider->getVersion(),
                'server_components_versions' => $provider->getComponentsVersions()
            ])) :
            new Response('', 500); // for later! MSP-3820
>>>>>>> 2ca4529ec25818827b8b6b61ac68c5f4c0a715e4
    }
}
