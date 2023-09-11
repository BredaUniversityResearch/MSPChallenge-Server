<?php

namespace App\Controller\ServerManager;

use App\Controller\SessionAPI\BaseController;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\Setting;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameListController extends AbstractController
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
        string $sessionState = 'public'
    ): Response {
        try {
            $clientVersion = $request->get('client_version');
            // might throw InvalidVersionString exception
            if (is_null($clientVersion) || !$provider->isCompatibleClient($clientVersion)) {
                //throw new \Exception('This client is incompatible.');
            }
        } catch (\Exception $e) {
            return new JsonResponse(
                BaseController::wrapPayloadForResponse([], $e->getMessage()),
                500
            );
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        $serverDesc = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        return is_null($request->headers->get('Turbo-Frame')) ?
            $this->json(BaseController::wrapPayloadForResponse([
                'sessionslist' => $gameList,
                'server_description' => $serverDesc->getValue(),
                'server_version' => $provider->getVersion()
            ])) :
            new Response('', 500); // for later! MSP-3820
    }
}
