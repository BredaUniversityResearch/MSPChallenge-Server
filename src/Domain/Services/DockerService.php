<?php

namespace App\Domain\Services;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class DockerService
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function createAndStartUnityService(int $port): void
    {
        $githubToken = $_ENV['GITHUB_PERSONAL_ACCESS_TOKEN'] ?? null;

        $client = HttpClient::create([
            'base_uri' => 'http://docker-remote-api:2375',
        ]);

        // Build the image from the Dockerfile
        $client->request('POST', '/build', [
            'query' => [
                't' => 'unity-server-image',
                'remote' => "https://{$githubToken}@github.com/BredaUniversityResearch/ImmersiveTwins-UnityServer-Docker.git",
            ]
        ]);

        // Create the container
        $response = $client->request('POST', '/containers/create', [
            'json' => [
                'Image' => 'unity-server-image', // Use the built image
                'HostConfig' => [
                    'PortBindings' => [
                        '50123/tcp' => [
                            ['HostPort' => (string)$port]
                        ]
                    ],
                    'LogConfig' => [
                        'Type' => 'local',
                    ]
                ],
                'Env' => [
                    'MSPXRClientPort=' . $port, // Pass the environment variable
                ],
            ],
        ]);

        // Extract the container ID from the response
        $containerId = json_decode($response->getContent(), true)['Id'];

        // Start the container
        $client->request('POST', "/containers/{$containerId}/start");
    }

    /**
     * @param int $port
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function createAndStartAdminerContainer(int $port): void
    {
        $client = HttpClient::create([
            'base_uri' => 'http://docker-remote-api:2375', // Docker API endpoint
        ]);

        // Pull the image
        $client->request('POST', '/images/create', [
            'query' => [
                'fromImage' => 'shyim/adminerevo',
            ],
            'headers' => [
                'X-Registry-Auth' => base64_encode('{}'), // Empty auth for public images
            ],
        ]);

        // Create the container
        $response = $client->request('POST', '/containers/create', [
            'json' => [
                'Image' => 'shyim/adminerevo',
                'HostConfig' => [
                    'PortBindings' => [
                        '8080/tcp' => [
                            ['HostPort' => (string)$port]
                        ]
                    ],
                    'LogConfig' => [
                        'Type' => 'local',
                    ]
                ],
                'Env' => [
                    'ADMINER_DEFAULT_DRIVER=MySQL',
                    'ADMINER_DEFAULT_SERVER=database',
                    'ADMINER_DEFAULT_USER='.($_ENV['DATABASE_USER'] ?? null),
                    'ADMINER_DEFAULT_PASSWORD='.($_ENV['DATABASE_PASSWORD'] ?? null),
                ],
            ],
        ]);

        // Extract the container ID from the response
        $containerId = json_decode($response->getContent(), true)['Id'];

        // Start the container
        $client->request('POST', "/containers/{$containerId}/start");
    }

    public function createHelloWorldContainer(): void
    {
        $client = HttpClient::create([
            'base_uri' => 'http://docker-remote-api:2375', // Docker API endpoint
        ]);

        // Create the container
        $client->request('POST', '/containers/create', [
            'json' => [
                'Image' => 'hello-world',
                'HostConfig' => [
                    'LogConfig' => [
                        'Type' => 'local',
                    ]
                ],
            ],
        ]);
    }
}
