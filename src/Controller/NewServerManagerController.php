<?php

namespace App\Controller;

use App\Domain\API\v1\Game;
use App\Entity\ServerManager\GameList;
use App\Form\NewSessionFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class NewServerManagerController extends AbstractController
{

    #[Route('/manager', name: 'app_new_server_manager')]
    public function index(): Response
    {
        return $this->render('manager/sessions.html.twig');
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
            return $this->render('manager/snippets/gamelist.html.twig', [
                'gameList' => $gameList
            ]);
        }
        return $this->redirectToRoute('app_new_server_manager');
    }

    #[Route('/manager/game/add', name: 'app_new_server_manager_gamelist_add')]
    public function newSessionForm(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus
    ): Response {
        $newGameSession = new GameList();
        $form = $this->createForm(NewSessionFormType::class, $newGameSession, [
            'entity_manager' => $entityManager,
            'action' => $this->generateUrl('app_new_server_manager_gamelist_add')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newGameSession = $form->getData();
            $entityManager->persist($newGameSession);
            $entityManager->flush();
            $messageBus->dispatch((new GameList($newGameSession->getId())));
            if ($request->isXmlHttpRequest()) {
                return new Response($newGameSession->getId(), 200);
            }
            $this->addFlash(
                'success',
                'Successfully added a new session. Please wait for it to be finalised.'
            );
            return $this->redirectToRoute('app_new_server_manager');
        }
        return $this->render('manager/GameList/new_game.html.twig', [
            'newSessionForm' => $form->createView()
        ], new Response(
            null,
            $form->isSubmitted() && !$form->isValid() ? 422 : 200,
        ));
    }

    #[Route('/manager/game/{id}/log', name: 'app_new_server_manager_game_log', requirements: ['id' => '\d+'])]
    public function getSessionLog(KernelInterface $kernel, int $id = 1): Response
    {
        $fileSystem = new FileSystem();
        $logPath = $kernel->getProjectDir()."/ServerManager/log/log_session_{$id}.log";
        if ($fileSystem->exists($logPath)) {
            $logArray = explode('<br />', nl2br(trim(file_get_contents($logPath))));
            $logArray = array_slice($logArray, -5);
            return $this->render('manager/GameList/sessionlog.html.twig', [
                'logToastBody' => $logArray
            ]);
        }
        return new Response(null, 422);
    }
}
