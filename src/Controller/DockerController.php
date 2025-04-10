<?php

namespace App\Controller;

use App\Domain\Services\DockerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class DockerController extends AbstractController
{
    public function __construct(private readonly DockerService $dockerService)
    {
    }

    #[Route('/api/docker/start-adminer', name: 'api_docker_start_adminer')]
    public function startAdminer(): Response
    {
        try {
            $this->dockerService->createAndStartAdminerContainer(8083);
        } catch (TransportExceptionInterface $e) {
            return new Response(
                'Error starting Adminer container: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response('Adminer container started successfully.');
    }

    #[Route('/api/docker/start-hello-world', name: 'api_docker_start_hello_world')]
    public function startHelloWorld(): Response
    {
        try {
            $this->dockerService->createHelloWorldContainer();
        } catch (TransportExceptionInterface $e) {
            return new Response(
                'Error starting Hello World container: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response('Hello World container started successfully.');
    }
}
