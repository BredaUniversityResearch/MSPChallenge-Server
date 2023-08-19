<?php

namespace App\Controller\ServerManager;

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class GameListController extends AbstractController
{

    #[Route('/manager', name: 'app_new_server_manager')]
    public function index(): Response
    {
        return $this->render('manager/gamelist_page.html.twig');
    }

    #[Route(
        '/manager/gamelist/{sessionState}',
        name: 'app_new_server_manager_game_list',
        requirements: ['sessionState' => '\w+']
    )]
    public function gameList(
        EntityManagerInterface $entityManager,
        Request $request,
        string $sessionState = 'public'
    ): Response {
        if (is_null($request->headers->get('Turbo-Frame'))) {
            return $this->redirectToRoute('app_new_server_manager');
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        return $this->render('manager/GameList/gamelist.html.twig', [
            'gameList' => $gameList
        ]);
    }

    #[Route(
        '/manager/game/{id}',
        name: 'app_new_server_manager_gamelist_handler',
        requirements: ['id' => '\d+']
    )]
    public function gameSessionForm(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id = 0
    ): Response {
        if ($id > 0) {
            $gameSession = $entityManager->getRepository(GameList::class)->find($id);
            $form = $this->createForm(GameListEditFormType::class, $gameSession, [
                'action' => $this->generateUrl('app_new_server_manager_gamelist_handler', ['id' => $id])
            ]);
        } else {
            $form = $this->createForm(GameListAddFormType::class, new GameList(), [
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('app_new_server_manager_gamelist_handler')
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
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 204)
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/manager/game/{id}/{action}/{param}',
        name: 'app_new_server_manager_game_actions',
        requirements: ['id' => '\d+', 'action' => '\w+', 'param' => '\w+']
    )]
    public function gameSessionAction(
        KernelInterface $kernel,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        int $id,
        string $action,
        string $param = ''
    ): Response {
        $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        if (is_null($gameSession)) {
            return new Response(null, 422);
        }
        switch ($action) {
            case 'log':
                $fileSystem = new FileSystem();
                $logPath = $kernel->getProjectDir()."/ServerManager/log/log_session_{$id}.log";
                if (!$fileSystem->exists($logPath)) {
                    return new Response(null, 422);
                }
                $logArray = explode('<br />', nl2br(trim(file_get_contents($logPath))));
                $logArray = array_slice($logArray, -5);
                return $this->render('manager/GameList/game_log.html.twig', [
                    'logToastBody' => $logArray
                ]);
            case 'state':
                if ($gameSession->getGameState() != $param && in_array($param, GameStateValue::getConstants())) {
                    // note: not persisting GameList object, because the websocket service takes care of that
                    $game = new Game();
                    $game->setGameSessionId($gameSession->getId());
                    $game->State($param);
                    return new Response(null, 204);
                }
                break;
            case 'recreate':
                if (!is_null($gameSession->getGameConfigVersion())) {
                    $messageBus->dispatch(new GameListSessionCreate($gameSession->getId()));
                    return new Response($gameSession->getId(), 200);
                }
                if (!is_null($gameSession->getGameSave())) {
                    //$messageBus->dispatch(new GameListSessionLoad($gameSession->getId()));
                    return new Response($gameSession->getId(), 200);
                }
                return new Response(null, 422);
            case 'archive':
                $gameSession->setSessionState(new GameSessionStateValue('archived'));
                $entityManager->persist($gameSession);
                $entityManager->flush();
                //$messageBus->dispatch(new GameListSessionArchive($gameSession->getId()));
                return new Response(null, 204);
            case 'save':
                $gameSave = (new GameSave)->createFromGameList($gameSession);
                $entityManager->persist($gameSave);
                $entityManager->flush();
                if ($param == 'full') {
                    //$messageBus->dispatch(new GameSaveFullSession($gameSave->getId()));
                }
                if ($param == 'layers') {
                    //$messageBus->dispatch(new GameSaveLayersSession($gameSave->getId()));
                }
                return new Response(null, 204);
            case 'export':
                // probably best to do this through the GameConfigFile class
                // should be immediately returned as a file download
                return new Response(null, 204);
            case 'download':
                if ($gameSession->getSessionState() != 'archived') {
                    //return new Response(null, 422);
                }
                // try to find the ZIP file
                // return it for immediate download
                return new Response(null, 422);
            case 'demo':
                $gameSession->setDemoSession(($gameSession->getDemoSession() === 1) ? 0 : 1);
                $entityManager->persist($gameSession);
                $entityManager->flush();
                return new Response(null, 204);
            default:
                return new Response(null, 422);
        }
        return new Response(null, 422);
    }
}
