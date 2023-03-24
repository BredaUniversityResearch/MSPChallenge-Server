<?php

namespace App\Controller;

use App\Entity\ServerManager\GameList;
use App\Form\NewSessionFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewServerManagerController extends AbstractController
{
    #[Route('/manager', name: 'app_new_server_manager')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $gameList = $entityManager->getRepository(GameList::class)->findAll();
        return $this->render('new_server_manager/index.html.twig', [
            'gameList' => $gameList
        ]);
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
