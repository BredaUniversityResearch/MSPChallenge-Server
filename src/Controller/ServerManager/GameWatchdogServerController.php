<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Form\GameWatchdogServerFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameWatchdogServerController extends BaseController
{
    #[Route('manager/gamewatchdogserver/list', name: 'manager_gamewatchdogserver_list')]
    public function gameWatchdogServerList(EntityManagerInterface $entityManager): Response
    {
        $gameWatchdogServers = $entityManager->getRepository(GameWatchdogServer::class)->findAll();
        return $this->render(
            'manager/GameWatchdogServer/gamewatchdogserver.html.twig',
            ['gameWatchdogServers' => $gameWatchdogServers]
        );
    }

    #[Route(
        'manager/gamewatchdogserver/{geoserverId}/availability',
        name: 'manager_gamewatchdogserver_visibility',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameWatchdogServerVisibility(EntityManagerInterface $entityManager, int $geoserverId): Response
    {
        $gameWatchdogServer = $entityManager->getRepository(GameWatchdogServer::class)->find($geoserverId);
        if ($gameWatchdogServer->getAvailable()) {
            $gameWatchdogServer->setAvailable(false);
        } else {
            $gameWatchdogServer->setAvailable(true);
        }
        $entityManager->flush();
        return new Response(null, 204);
    }

    #[Route(
        'manager/gamewatchdogserver/{geoserverId}/form',
        name: 'manager_gamewatchdogserver_form',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameWatchdogServerForm(
        EntityManagerInterface $entityManager,
        Request $request,
        int $geoserverId
    ): Response {
        $form = $this->createForm(
            GameWatchdogServerFormType::class,
            $geoserverId == 0 ?
                new GameWatchdogServer : $entityManager->getRepository(GameWatchdogServer::class)->find($geoserverId),
            ['action' => $this->generateUrl('manager_gamewatchdogserver_form', ['geoserverId' => $geoserverId])]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameWatchdogServer = $form->getData();
            if ($geoserverId == 0) {
                $entityManager->persist($gameWatchdogServer);
            }
            $entityManager->flush();
        }
        return $this->render(
            'manager/GameWatchdogServer/gamewatchdogserver_form.html.twig',
            ['gameWatchdogServerForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
