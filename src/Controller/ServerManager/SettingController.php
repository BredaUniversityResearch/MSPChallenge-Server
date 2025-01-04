<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;
use App\Domain\Communicator\Auth2Communicator;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\ServerManager\Setting;
use App\Form\SettingEditFormType;
use App\Form\SettingUsersFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

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

    #[Route('/manager/setting/{settingId}/form', name: 'manager_setting_form', requirements: ['settingId' => '\d+'])]
    public function settingForm(EntityManagerInterface $entityManager, Request $request, int $settingId): Response
    {
        $setting = $entityManager->getRepository(Setting::class)->find($settingId);
        $form = $this->createForm(
            SettingEditFormType::class,
            $setting,
            ['action' => $this->generateUrl('manager_setting_form', ['settingId' => $settingId])]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $setting = $form->getData();
            $entityManager->flush();
        }
        return $this->render(
            'manager/Setting/setting_form.html.twig',
            ['settingForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('manager/setting/users/list', name: 'manager_setting_users')]
    public function settingUsers(
        EntityManagerInterface $entityManager,
        HttpClientInterface $client,
        Security $security
    ): Response {
        $auth2Communicator = new Auth2Communicator($client);
        // @var App\Entity\ServerManager\User
        $user = $security->getUser();
        $auth2Communicator->setToken($user->getToken());
        $auth2Result = $auth2Communicator->getResource(
            "servers/{$entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_uuid'])->getValue()}
                /server_users"
        );
        return $this->render(
            'manager/Setting/setting_users_list.html.twig',
            ['returns' => $auth2Result['hydra:member']]
        );
    }

    #[Route('manager/setting/users/delete', name: 'manager_setting_users_delete')]
    public function settingUsersDelete(
        HttpClientInterface $client,
        Security $security,
        Request $request
    ): Response {
        $auth2Communicator = new Auth2Communicator($client);
        // @var App\Entity\ServerManager\User
        $user = $security->getUser();
        $auth2Communicator->setToken($user->getToken());
        try {
            $auth2Communicator->delResource(str_replace('/api/', '', $request->get('delurl')));
        } catch (Throwable $e) {
            return new Response($e->getMessage(), 422);
        }
        return new Response(null, 204);
    }

    #[Route('manager/setting/users/form', name: 'manager_setting_users_form')]
    public function settingUsersForm(
        HttpClientInterface $client,
        EntityManagerInterface $entityManager,
        Security $security,
        Request $request
    ): Response {
        $form = $this->createForm(
            SettingUsersFormType::class,
            null,
            ['action' => $this->generateUrl('manager_setting_users_form')]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedUser = $form->get('username')->getData();
            $endPoint = filter_var($submittedUser, FILTER_VALIDATE_EMAIL) ?
                "users?email={$submittedUser}" :
                "users?username={$submittedUser}";
            $auth2Communicator = new Auth2Communicator($client);
            // @var App\Entity\ServerManager\User
            $user = $security->getUser();
            $auth2Communicator->setToken($user->getToken());
            try {
                $auth2Result = $auth2Communicator->getResource($endPoint);
                if (!empty($auth2Result['hydra:member'])) {
                    $serverUUID = $entityManager->getRepository(Setting::class)
                        ->findOneBy(['name' => 'server_uuid'])->getValue();
                    $auth2Communicator->postResource(
                        'server_users',
                        [
                            'server' => "/api/servers/{$serverUUID}",
                            'user' => $auth2Result['hydra:member'][0]['@id'],
                            'isAdmin' => $form->get('isAdmin')->getData()
                        ]
                    );
                } else {
                    $form->get('username')->addError(new FormError('User not found'));
                }
            } catch (Throwable $e) {
                return new Response($e->getMessage(), 422);
            }
        }
        return $this->render(
            'manager/Setting/setting_users_form.html.twig',
            ['settingUsersForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
