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
use App\Entity\ServerManager\User;
use App\Form\SettingEditFormType;
use App\Form\SettingUsersFormType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[Route('/{manager}/setting', requirements: ['manager' => 'manager|ServerManager'], defaults: ['manager' => 'manager'])]
class SettingController extends BaseController
{
    #[Route(name: 'manager_setting')]
    public function index(): Response
    {
        return $this->render('manager/setting_page.html.twig');
    }

    #[Route('/reset/{type}', name: 'manager_setting_reset', requirements: ['type' => '\d+'])]
    public function settingReset(
        KernelInterface $kernel,
        int $type = 0
    ): Response {
        if ($type == 0) {
            return $this->render('manager/Setting/setting_reset.html.twig');
        }
        $command = ['command' => 'app:reset', '--force' => true];
        if ($type == 1) {
            $command['sessions'] = '1-999';
        }
        $app = new Application($kernel);
        $app->setAutoExit(false);
        $input = new ArrayInput($command);
        $output = new BufferedOutput();
        $app->run($input, $output);
        return $this->render('manager/Setting/setting_reset.html.twig', [
            'resetReturn' => $output->fetch(),
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/list', name: 'manager_setting_list')]
    public function settingList(): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $setting = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        $gameServer = $entityManager->getRepository(GameServer::class)->find(1);
        $gameGeoServersAll = count($entityManager->getRepository(GameGeoServer::class)->findAll());
        $gameGeoServersAvailable = count(
            $entityManager->getRepository(GameGeoServer::class)->findBy(['available' => 1])
        );
        $gameWatchdogServersAll = count($entityManager->getRepository(GameWatchdogServer::class)->findAll());
        $gameWatchdogServersAvailable = count(
            $entityManager->getRepository(GameWatchdogServer::class)->findBy(['available' => 1])
        );
        return $this->render(
            'manager/Setting/setting.html.twig',
            [
                'setting' => $setting,
                'gameServer' => $gameServer,
                'gameGeoServers' => [$gameGeoServersAll, $gameGeoServersAvailable],
                'gameWatchdogServers' => [$gameWatchdogServersAll, $gameWatchdogServersAvailable]
            ]
        );
    }

    /**
     * @throws \Exception
     */
    #[Route('/{settingId}/form', name: 'manager_setting_form', requirements: ['settingId' => '\d+'])]
    public function settingForm(Request $request, int $settingId): Response
    {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
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

    /**
     * @throws \Exception
     */
    #[Route('/users/list', name: 'manager_setting_users')]
    public function settingUsers(
        HttpClientInterface $client,
        Security $security
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $auth2Communicator = new Auth2Communicator($client);
        /** @var User $user */
        $user = $security->getUser();
        $auth2Communicator->setToken($user->getToken());
        $serverUUID = $entityManager->getRepository(Setting::class)->findOneBy(['name' => 'server_uuid'])->getValue();
        $auth2Result = $auth2Communicator->getResource("servers/{$serverUUID}/server_users");
        return $this->render(
            'manager/Setting/setting_users_list.html.twig',
            ['returns' => $auth2Result['hydra:member']]
        );
    }

    #[Route('/users/delete', name: 'manager_setting_users_delete')]
    public function settingUsersDelete(
        HttpClientInterface $client,
        Security $security,
        Request $request
    ): Response {
        $auth2Communicator = new Auth2Communicator($client);
        /** @var User $user */
        $user = $security->getUser();
        $auth2Communicator->setToken($user->getToken());
        try {
            $auth2Communicator->delResource(str_replace('/api/', '', $request->get('delurl')));
        } catch (Throwable $e) {
            return new Response($e->getMessage(), 422);
        }
        return new Response(null, 204);
    }

    /**
     * @throws \Exception
     */
    #[Route('/users/form', name: 'manager_setting_users_form')]
    public function settingUsersForm(
        HttpClientInterface $client,
        Security $security,
        Request $request
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
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
            /** @var User $user */
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
