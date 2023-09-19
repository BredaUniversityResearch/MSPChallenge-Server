<?php

namespace App\Controller\ServerManager;

use App\Controller\SessionAPI\BaseController;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\Setting;
use App\IncompatibleClientException;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Version\Exception\InvalidVersionString;

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
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        string $sessionState = 'public'
    ): Response {
        try {
            $provider->checkCompatibleClient($request->headers->get('Msp-Client-Version'));
        } catch (IncompatibleClientException $e) {
            return new JsonResponse(
                BaseController::wrapPayloadForResponse(
                    [
                        'clients_url' => 'https://community.mspchallenge.info/wiki/Download',
                        'server_version' => $provider->getVersion()
                    ],
                    $e->getMessage()
                ),
                403
            );
        } catch (InvalidVersionString $e) {
            return new JsonResponse(BaseController::wrapPayloadForResponse([], $e->getMessage()), 400);
        }
        $gameList = $entityManager->getRepository(GameList::class)->findBySessionState($sessionState);
        $serverDesc = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        return is_null($request->headers->get('Turbo-Frame')) ?
            $this->json(BaseController::wrapPayloadForResponse([
                'sessionslist' => $gameList,
                'server_description' => $serverDesc->getValue(),
                'server_version' => $provider->getVersion(),
                'server_components_versions' => $provider->getComponentsVersions()
            ])) :
            new Response('', 500); // for later! MSP-3820
    }
}
