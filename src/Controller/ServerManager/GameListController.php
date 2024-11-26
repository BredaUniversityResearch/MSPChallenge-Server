<?php

namespace App\Controller\ServerManager;

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
                    false,
                    [
                        'clients_url' => $this->getParameter('app.clients_url'),
                        'server_version' => $provider->getVersion()
                    ],
                    $e->getMessage()
                ),
                403
            );
        } catch (InvalidVersionString $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 400);
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        $serverDesc = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        return is_null($request->headers->get('Turbo-Frame')) ?
            $this->json(self::wrapPayloadForResponse(
                true,
                [
                    'sessionslist' => $gameList,
                    'server_description' => $serverDesc->getValue(),
                    'clients_url' => $this->getParameter('app.clients_url'),
                    'server_version' => $provider->getVersion(),
                    'server_components_versions' => $provider->getComponentsVersions()
                ]
            )) :
            new Response('', 500); // for later! MSP-3820
    }
}
