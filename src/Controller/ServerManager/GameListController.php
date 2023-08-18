<?php

namespace App\Controller\ServerManager;

use App\Entity\ServerManager\GameList;
use App\Form\GameListFormType;
use App\Messages\GameListSessionCreation;
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
        if (!is_null($request->headers->get('Turbo-Frame'))) {
            $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
            return $this->render('manager/GameList/gamelist.html.twig', [
                'gameList' => $gameList
            ]);
        }
        return $this->redirectToRoute('app_new_server_manager');
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
        $gameSession = new GameList();
        if ($id > 0) {
            $gameSession = $entityManager->getRepository(GameList::class)->find($id);
        }
        $form = $this->createForm(GameListFormType::class, $gameSession, [
            'entity_manager' => $entityManager,
            'action' => $this->generateUrl('app_new_server_manager_gamelist_handler')
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            $entityManager->persist($gameSession);
            $entityManager->flush();
            if ($gameSession->getSessionState() == 'request' && !is_null($gameSession->getGameConfigVersion())) {
                $messageBus->dispatch((new GameListSessionCreation($gameSession->getId())));
            }
            return new Response($gameSession->getId(), 200);
        }
        return $this->render(
            $id === 0 ? 'manager/GameList/new_game.html.twig': 'manager/GameList/existing_game.html.twig',
            ['gameSessionForm' => $form->createView()],
            new Response(
                null,
                $form->isSubmitted() && !$form->isValid() ? 422 : 200
            )
        );
    }

    #[Route(
        '/manager/game/{id}/log',
        name: 'app_new_server_manager_game_log',
        requirements: ['id' => '\d+']
    )]
    public function getSessionLog(KernelInterface $kernel, int $id = 1): Response
    {
        $fileSystem = new FileSystem();
        $logPath = $kernel->getProjectDir()."/ServerManager/log/log_session_{$id}.log";
        if ($fileSystem->exists($logPath)) {
            $logArray = explode('<br />', nl2br(trim(file_get_contents($logPath))));
            $logArray = array_slice($logArray, -5);
            return $this->render('manager/GameList/game_log.html.twig', [
                'logToastBody' => $logArray
            ]);
        }
        return new Response(null, 422);
    }
}
