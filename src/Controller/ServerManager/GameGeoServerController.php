<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameGeoServer;
use App\Form\GameGeoServerFormType;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/{manager}/gamegeoserver',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameGeoServerController extends BaseController
{
    /**
     * @throws Exception
     */
    #[Route('/list', name: 'manager_gamegeoserver_list')]
    public function gameGeoServerList(): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameGeoServers = $entityManager->getRepository(GameGeoServer::class)->findAll();
        return $this->render('manager/GameGeoServer/gamegeoserver.html.twig', ['gameGeoServers' => $gameGeoServers]);
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{geoserverId}/availability',
        name: 'manager_gamegeoserver_visibility',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameGeoServerVisibility(int $geoserverId): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameGeoServer = $entityManager->getRepository(GameGeoServer::class)->find($geoserverId);
        if ($gameGeoServer->getAvailable()) {
            $gameGeoServer->setAvailable(false);
        } else {
            $gameGeoServer->setAvailable(true);
        }
        $entityManager->flush();
        return new Response(null, 204);
    }

    /**
     * @throws Exception
     */
    #[Route(
        '/{geoserverId}/form',
        name: 'manager_gamegeoserver_form',
        requirements: ['geoserverId' => '\d+']
    )]
    public function gameGeoServerForm(
        Request $request,
        int $geoserverId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $form = $this->createForm(
            GameGeoServerFormType::class,
            $geoserverId == 0 ?
                new GameGeoServer : $entityManager->getRepository(GameGeoServer::class)->find($geoserverId),
            ['action' => $this->generateUrl('manager_gamegeoserver_form', ['geoserverId' => $geoserverId])]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameGeoServer = $form->getData();
            if ($geoserverId == 0) {
                $entityManager->persist($gameGeoServer);
            }
            $entityManager->flush();
        }
        return $this->render(
            'manager/GameGeoServer/gamegeoserver_form.html.twig',
            ['gameGeoServerForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
