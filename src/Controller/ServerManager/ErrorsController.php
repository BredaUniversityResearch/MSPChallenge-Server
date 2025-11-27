<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Services\DockerApiService;
use App\Entity\ServerManager\DockerApi;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{manager}/error', requirements: ['manager' => 'manager|ServerManager'], defaults: ['manager' => 'manager'])]
class ErrorsController extends BaseController
{
    /**
     * @throws Exception
     */
    #[Route(name: 'manager_errors')]
    public function index(Request $request, DockerApiService $dockerApiService): Response
    {
        return $this->render('manager/errors_page.html.twig');
    }

    /**
     * @throws Exception
     */
    #[Route('/logs/stream', name: 'manager_error_logs_stream')]
    public function streamLogs(Request $request, DockerApiService $dockerApiService): Response
    {
        $localDockerApi = new DockerApi();
        $localDockerApi
            ->setPort(2375)
            ->setAddress('localhost')
            ->setScheme('http');
        $fluentBitContainerId = $dockerApiService->dockerApiCall($localDockerApi, 'GET', '/containers/json', [
            'query' => [
                'filters' => '{"label": ["com.docker.compose.service=fluent-bit"]}'
            ],
        ])['0']['Id'] ?? null;
        if ($fluentBitContainerId === null) {
            return new Response('No fluent-bit container found.', 404);
        }

        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->setCallback(function () use ($dockerApiService, $localDockerApi, $fluentBitContainerId) {
            $ch = curl_init();
            $url = "http://localhost/containers/{$fluentBitContainerId}/logs?follow=1&stdout=1&stderr=1&tail=100";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/docker.sock');
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                foreach (explode("\n", $data) as $line) {
                    // Capture the first JSON object in the line
                    if (preg_match('/\{.*\}/s', $line, $matches)) {
                        $jsonStr = $matches[0];
                        $decoded = json_decode($jsonStr, true);
                        $decoded['message'] ??= $decoded['log'];
                        unset($decoded['log']);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            echo "event: log\ndata: " . json_encode($decoded) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }
                }
                return strlen($data);
            });
            curl_exec($ch);
            curl_close($ch);
        });
        return $response;
    }
}
