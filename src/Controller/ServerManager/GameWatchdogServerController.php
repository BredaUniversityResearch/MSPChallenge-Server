<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Form\GameWatchdogServerFormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/{manager}/gamewatchdogserver',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameWatchdogServerController extends BaseController
{
    /**
     * @throws \Exception
     */
    #[Route('/list', name: 'manager_gamewatchdogserver_list')]
    public function gameWatchdogServerList(): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameWatchdogServers = $entityManager->getRepository(GameWatchdogServer::class)->findAll();
        return $this->render(
            'manager/GameWatchdogServer/gamewatchdogserver.html.twig',
            ['gameWatchdogServers' => $gameWatchdogServers]
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{geoserverId}/availability',
        name: 'manager_gamewatchdogserver_visibility',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameWatchdogServerVisibility(int $geoserverId): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameWatchdogServer = $entityManager->getRepository(GameWatchdogServer::class)->find($geoserverId);
        if ($gameWatchdogServer->getAvailable()) {
            $gameWatchdogServer->setAvailable(false);
        } else {
            $gameWatchdogServer->setAvailable(true);
        }
        $entityManager->flush();
        return new Response(null, 204);
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{geoserverId}/form',
        name: 'manager_gamewatchdogserver_form',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameWatchdogServerForm(
        Request $request,
        int $geoserverId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
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
        $f = $form->createView();
        return $this->render(
            'manager/GameWatchdogServer/gamewatchdogserver_form.html.twig',
            ['gameWatchdogServerForm' => $f],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
