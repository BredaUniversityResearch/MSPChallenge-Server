<?php

namespace App\Controller;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\ServerManager\GameList;
use App\Form\NewSessionFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewServerManagerController extends AbstractController
{

    #[Route('/manager', name: 'app_new_server_manger')]
    public function index(): Response
    {
        return $this->render('new_server_manager/sessions.html.twig');
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
            return $this->render('new_server_manager/snippets/gamelist.html.twig', [
                'gameList' => $gameList
            ]);
        }
        return $this->redirectToRoute('app_new_server_manger');
    }

    #[Route('/manager/gamelist/add', name: 'app_new_server_manager_gamelist_add')]
    public function newSessionForm(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(NewSessionFormType::class, null, [
            'entity_manager' => $entityManager,
            'action' => $this->generateUrl('app_new_server_manager_gamelist_add'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // do nothing for now
            $this->addFlash('success', 'Added a game session.');
            return $this->redirectToRoute('app_new_server_manager');
        }
        return $this->render('new_server_manager/GameList/new_game.html.twig', [
            'newSessionForm' => $form->createView()
        ]);
    }
}
