<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewServerManagerController extends AbstractController
{
    #[Route('/manager', name: 'app_new_server_manager')]
    public function index(): Response
    {
        return $this->render('new_server_manager/index.html.twig', [
            'controller_name' => 'NewServerManagerController',
        ]);
    }
}
