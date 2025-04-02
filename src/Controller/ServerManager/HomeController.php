<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/{m}anager',
    name: 'manager',
    requirements: ['m' => 'm|ServerM'],
    defaults: [ 'm' => 'm']
)]
class HomeController extends BaseController
{
    public function __invoke(): Response
    {
        return $this->render('manager/gamelist_page.html.twig');
    }
}
