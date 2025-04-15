<?php

namespace App\Domain\Services;

use App\Entity\ImmersiveSession;
use App\Entity\ImmersiveSessionConnection;
use App\Entity\ServerManager\ImmersiveSessionDockerApi;
use App\Entity\ServerManager\ImmersiveSessionType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ImmersiveSessionService
{
    private ?ImmersiveSessionDockerApi $currentDockerApi;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly \Predis\Client $redisClient
    ) {
        $this->currentDockerApi = null;
    }

    /**
     * @throws Exception
     */
    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = $this->connectionManager->getServerManagerEntityManager();
        return $em;
    }

    /**
     * @throws Exception
     */
    private function getDockerApi(): ImmersiveSessionDockerApi
    {
        if ($this->currentDockerApi !== null) {
            // Return the cached API for this request
            return $this->currentDockerApi;
        }

        /** @var ImmersiveSessionDockerApi[] $dockerApis */
        $dockerApis = $this->getEntityManager()
            ->getRepository(ImmersiveSessionDockerApi::class)->findAll();
        if (empty($dockerApis)) {
            throw new Exception('No Docker APIs found in the database.');
        }

        // round-robin selection of the Docker API
        $indexKey = 'docker_api_index';
        try {
            // Atomically increment the index in Redis and get the new value
            //   if it doesn't exist, it will be created with 0 and increased to 1
            $currentIndex = $this->redisClient->incr($indexKey);
        } catch (\Exception $e) {
            if ($e->getMessage() == 'increment or decrement would overflow') {
                $this->redisClient->set($indexKey, 1);
                $currentIndex = 1;
            } else {
                // Handle other Redis errors
                throw new Exception('Redis error: ' . $e->getMessage());
            }
        }

        // Wrap around the index using modulo operation
        $nextIndex = ($currentIndex - 1) % count($dockerApis);

        // Store the selected API in the member variable
        $this->currentDockerApi = $dockerApis[$nextIndex];
        return $this->currentDockerApi;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function createImmersiveSessionContainer(ImmersiveSession $sess): void
    {
        $githubToken = ($_ENV['GITHUB_PERSONAL_ACCESS_TOKEN'] ?? null) ?: null;
        if ($githubToken === null) {
            throw new Exception('GITHUB_PERSONAL_ACCESS_TOKEN is not set.');
        }
        $immersiveSessionType = $this->getEntityManager()->getRepository(ImmersiveSessionType::class)
            ->findOneBy(['type' => $sess->getType()]);
        if ($immersiveSessionType === null) {
            throw new Exception('Immersive session type not found: ' . $sess->getType()->value);
        }

        // find all connections for this docker api, get the max port and add 1
        $nextAvailablePort = collect(
            $this->getEntityManager()->getRepository(ImmersiveSessionConnection::class)->findBy(
                ['dockerApiID' => $this->getDockerApi()->getId()]
            )
        )->reduce(
            fn ($carry, ImmersiveSessionConnection $connection) => max($carry, $connection->getPort()),
            0
        ) + 1;
        $conn = new ImmersiveSessionConnection();
        $conn
            ->setSession($sess)
            ->setDockerApiID($this->getDockerApi()->getId())
            ->setPort($nextAvailablePort);

        // Create the container
        $client = HttpClient::create([
            'base_uri' => $this->getDockerApi()->createUrl(),
        ]);

        $branchName = ($_ENV['IMMERSIVE_TWINS_DOCKER_BRANCH'] ?? null) ?: 'main';
        $client->request('POST', '/build', [
            'query' => [
                't' => 'unity-server-image',
                'remote' =>
                    "https://{$githubToken}@github.com/BredaUniversityResearch/ImmersiveTwins-UnityServer-Docker.git".
                        "#{$branchName}"
            ]
        ]);
        $data = array_merge(
            $immersiveSessionType->getDataDefault() ?? [],
            $sess->getData() ?? []
        );
        $response = $client->request('POST', '/containers/create', [
            'json' => [
                'Image' => 'unity-server-image', // Use the built image
                'HostConfig' => [
                    'PortBindings' => [
                        '50123/tcp' => [
                            ['HostPort' => (string)$conn->getPort()]
                        ]
                    ],
                    'LogConfig' => [
                        'Type' => 'local',
                    ]
                ],
                'Env' => [
                    'IMMERSIVE_SESSION_MONTH='.$conn->getSession()->getMonth(),
                    'IMMERSIVE_SESSION_DATA='.json_encode($data)
                    //'MSPXRClientPort=50123', // Pass the environment variable
                ],
            ],
        ]);

        // Extract the container ID from the response
        $containerId = json_decode($response->getContent(), true)['Id'];

        // Start the container
        $client->request('POST', "/containers/{$containerId}/start");

        $conn->setDockerContainerID($containerId);
        $this->getEntityManager()->persist($conn);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws TransportExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
    public function removeImmersiveSessionContainer(ImmersiveSession $sess): void
    {
        $conn = $sess->getConnection();
        if ($conn === null) {
            throw new Exception('No connection found for this session.');
        }

        // Create the HTTP client
        $client = HttpClient::create([
            'base_uri' => $this->getDockerApi()->createUrl(),
        ]);

        // Stop the container
        $client->request('POST', "/containers/{$conn->getDockerContainerId()}/stop");

        // Remove the container
        $client->request('DELETE', "/containers/{$conn->getDockerContainerId()}");

        // Remove the connection from the database
        $this->getEntityManager()->remove($conn);
        $this->getEntityManager()->flush();
    }
}
