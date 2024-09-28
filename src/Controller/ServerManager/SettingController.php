<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\BaseController;

class SettingController extends BaseController
{

    public function __construct()
    {
    }

    #[Route('/manager/settings', name: 'manager_setting')]
    public function index(): Response
    {
        return $this->render('manager/setting_page.html.twig');
    }
}
