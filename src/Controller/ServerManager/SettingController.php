<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\ServerManager\Setting;
use Doctrine\ORM\EntityManagerInterface;

class SettingController extends BaseController
{
    #[Route('/manager/setting', name: 'manager_setting')]
    public function index(): Response
    {
        return $this->render('manager/setting_page.html.twig');
    }

    #[Route('manager/setting/list', name: 'manager_setting_list')]
    public function settingList(EntityManagerInterface $entityManager): Response
    {
        $setting = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        $gameServer = $entityManager->getRepository(GameServer::class)->find(1);
        $gameGeoServers = count($entityManager->getRepository(GameGeoServer::class)->findAll());
        $gameWatchdogServers = count($entityManager->getRepository(GameWatchdogServer::class)->findAll());
        return $this->render(
            'manager/Setting/setting.html.twig',
            [
                'setting' => $setting,
                'gameServer' => $gameServer,
                'gameGeoServers' => $gameGeoServers,
                'gameWatchdogServers' => $gameWatchdogServers
            ]
        );
    }
}
