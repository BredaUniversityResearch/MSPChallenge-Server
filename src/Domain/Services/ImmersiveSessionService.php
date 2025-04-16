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
    public function createImmersiveSessionContainer(ImmersiveSession $sess, int $gameSessionId): void
    {
        $immersiveSessionType = $this->getEntityManager()->getRepository(ImmersiveSessionType::class)
            ->findOneBy(['type' => $sess->getType()]);
        if ($immersiveSessionType === null) {
            throw new Exception('Immersive session type not found: ' . $sess->getType()->value);
        }

        // find all connections for this docker api, get the max port and add 1
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $nextAvailablePort = collect(
            $em
                ->getRepository(ImmersiveSessionConnection::class)->findBy(
                    ['dockerApiID' => $this->getDockerApi()->getId()]
                )
        )->reduce(
            fn ($carry, ImmersiveSessionConnection $connection) => max($carry, $connection->getPort()),
            45000
        ) + 1;
        $conn = new ImmersiveSessionConnection();
        $conn
            ->setSession($sess)
            ->setDockerApiID($this->getDockerApi()->getId())
            ->setPort($nextAvailablePort);

        $branchName = ($_ENV['IMMERSIVE_TWINS_DOCKER_BRANCH'] ?? null) ?: 'main';
        $this->dockerApiCall('POST', '/build', [
            'query' => [
                't' => 'unity-server-image',
                'remote' => 'https://github.com/BredaUniversityResearch/ImmersiveTwins-UnityServer-Docker.git#'.
                    $branchName,
            ]
        ]);

        $data = array_merge(
            $immersiveSessionType->getDataDefault() ?? [],
            $sess->getData() ?? []
        );
        $responseContent = $this->dockerApiCall('POST', '/containers/create', [
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

        // Start the container
        $containerId = $responseContent['Id'];
        $this->dockerApiCall('POST', "/containers/{$containerId}/start");
        $conn->setDockerContainerID($containerId);
        $em->persist($conn);
        $em->flush();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function removeImmersiveSessionContainer(ImmersiveSession $sess, int $gameSessionId): void
    {
        $conn = $sess->getConnection();
        if ($conn === null) {
            throw new Exception('No connection found for this session.');
        }
        $this->dockerApiCall('POST', "/containers/{$conn->getDockerContainerId()}/stop");
        $this->dockerApiCall('DELETE', "/containers/{$conn->getDockerContainerId()}");
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $em->remove($conn);
        $em->flush();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    private function dockerApiCall(string $method, string $path, array $options = []): ?array
    {
        $client = HttpClient::create([
            'base_uri' => $this->getDockerApi()->createUrl(),
        ]);

        $response = $client->request($method, $path, $options);

        // Get the response content without throwing exceptions for HTTP errors
        $responseContent = $response->getContent(false);
        // Check the HTTP status code
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Docker API error: '.$response->getStatusCode().': '.$responseContent);
        }

        return json_decode($responseContent, true);
    }
}
