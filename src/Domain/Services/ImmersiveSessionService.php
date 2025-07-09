<?php

namespace App\Domain\Services;

use App\Entity\ServerManager\DockerApi;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\ImmersiveSessionType;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Entity\SessionAPI\ImmersiveSessionConnection;
use App\MessageHandler\Watchdog\WatchdogCommunicationMessageHandler;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;

class ImmersiveSessionService
{
    private ?DockerApi $currentDockerApi;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly \Predis\Client $redisClient,
        private readonly LoggerInterface $dockerLogger
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
    private function getDockerApi(): DockerApi
    {
        if ($this->currentDockerApi !== null) {
            // Return the cached API for this request
            return $this->currentDockerApi;
        }

        /** @var DockerApi[] $dockerApis */
        $dockerApis = $this->getEntityManager()
            ->getRepository(DockerApi::class)->findAll();
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
     * @throws Exception
     */
    public function createImmersiveSessionContainer(ImmersiveSession $sess, int $gameSessionId): void
    {
        if (null == $gameList = $this->connectionManager->getServerManagerEntityManager()
            ->getRepository(GameList::class)
            ->find($gameSessionId)
        ) {
            throw new Exception('Game list not found. Id: '.$gameSessionId);
        }

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
            (
                max(1, (int)($_ENV['IMMERSIVE_SESSION_CONNECTION_PORT_START'] ?? 45100))
            ) - 1
        ) + 1;
        $conn = new ImmersiveSessionConnection();
        $conn
            ->setSession($sess)
            ->setDockerApiID($this->getDockerApi()->getId())
            ->setPort($nextAvailablePort);

        $data = array_merge(
            $immersiveSessionType->getDataDefault() ?? [],
            $sess->getData() ?? []
        );
        // Pull the image before creating the container
        $tag = $_ENV['IMMERSIVE_TWINS_DOCKER_HUB_TAG'] ?? 'latest';
        $this->dockerApiCall('POST', '/images/create', [
            'query' => [
                'fromImage' => 'docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server',
                'tag' => $tag
            ],
        ]);
        $responseContent = $this->dockerApiCall('POST', '/containers/create', [
            'json' => [
               'Image' => 'docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server:'.$tag,
               'ExposedPorts' => [
                    '50123/udp' => new \stdClass() // Explicitly expose the port
                ],
                'HostConfig' => [
                   'PortBindings' => [
                        '50123/udp' => [
                            ['HostPort' => (string)$conn->getPort()]
                        ]
                    ]
                ],
                'Env' => [
                    'MSP_CHALLENGE_SESSION_ID='.$gameSessionId,
                    'MSP_CHALLENGE_API_BASE_URL_FOR_SERVER='.WatchdogCommunicationMessageHandler::getSessionAPIBaseUrl(
                        $gameList,
                        $this->getDockerApi()->getAddress() == 'host.docker.internal' ? 'host.docker.internal' : null
                    ),
                    'MSP_CHALLENGE_API_BASE_URL_FOR_CLIENT='.WatchdogCommunicationMessageHandler::getSessionAPIBaseUrl(
                        $gameList
                    ),
                    'IMMERSIVE_SESSION_REGION_COORDS='.json_encode([
                        'region_bottom_left_x' => $sess->getRegion()->getBottomLeftX(),
                        'region_bottom_left_y' => $sess->getRegion()->getBottomLeftY(),
                        'region_top_right_x' => $sess->getRegion()->getTopRightX(),
                        'region_top_right_y' => $sess->getRegion()->getTopRightY()
                    ]),
                    'IMMERSIVE_SESSION_MONTH='.$conn->getSession()->getMonth(),
                    // like require_username, require_team, gamemaster_pick
                    'IMMERSIVE_SESSION_DATA='.json_encode($data)
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
     * @throws Exception
     */
    public function removeImmersiveSessionContainer(ImmersiveSession $sess, int $gameSessionId): void
    {
        $conn = $sess->getConnection();
        if ($conn === null) {
            throw new Exception('No connection found for this session.');
        }

        $dockerContainerId = $conn->getDockerContainerId();
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $em->remove($conn);
        $em->flush();

        $this->dockerApiCall('POST', "/containers/{$dockerContainerId}/stop");
        $this->dockerApiCall('DELETE', "/containers/{$dockerContainerId}");
    }

    /**
     * @throws Exception
     */
    private function dockerApiCall(string $method, string $path, array $options = []): ?array
    {
        // Build the query string
        $queryString = http_build_query($options['query'] ?? []);
        $fullUrl = $this->getDockerApi()->createUrl() . $path . ($queryString ? '?' . $queryString : '');

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream the response directly
        $responseContent = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$responseContent) {
            $this->dockerLogger->info($data);
            $responseContent .= $data;
            return strlen($data);
        });

        // Set the POST fields if there is json data
        if (!empty($options['json'] ?? [])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        // Execute the request
        curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('curl error: ' . $error);
        }

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL handle
        curl_close($ch);

        // Check for HTTP errors
        if ($httpCode >= 400) {
            throw new \RuntimeException('Docker API error: ' . $httpCode . ': ' . $responseContent);
        }

        return json_decode($responseContent, true);
    }
}
