<?php

namespace App\Domain\Services;

use App\Domain\Common\EntityEnums\ImmersiveSessionStatus;
use App\Entity\ServerManager\DockerApi;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\ImmersiveSessionType;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Entity\SessionAPI\DockerConnection;
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
    public function pickDockerApi(): DockerApi
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
    public function createImmersiveSessionContainer(
        DockerApi $dockerApi,
        ImmersiveSession $sess,
        int $gameSessionId
    ): void {
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

        $sess
            ->setStatusResponse([
                'message' => 'Starting container...'
            ]);
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $em->persist($sess);
        $em->flush();

        $gameSessionBusyPorts = collect($em->getRepository(DockerConnection::class)->findAll())
            ->map(fn($conn) => $conn->getPort())->all();
        // find next available port
        $initialPort = (int)($_ENV['IMMERSIVE_SESSIONS_CONNECTION_PORT_START'] ?? 45100);
        /** @var int[] $busyPorts */
        $busyPorts = collect($this->listImmersiveSessionContainers($dockerApi))->map(
            /** @var array{Id: string, State: string, Ports: array{PublicPort: int, PrivatePort: int, Type: string}} $item */
            fn($item) => $item['Ports']['PublicPort'] ?? null
        )
        ->filter() // removes all null values
        ->push(...$gameSessionBusyPorts)->unique()->sort()->all();
        $nextAvailablePort = $initialPort;
        $nextBusyPort = current($busyPorts); // if $busyPorts is empty, this will be false
        while ($nextAvailablePort === $nextBusyPort) {
            $nextAvailablePort++;
            $nextBusyPort = next($busyPorts);
        }

        $conn = new DockerConnection();
        $conn
            ->setDockerApiID($dockerApi->getId())
            ->setPort($nextAvailablePort);

        $data = array_merge(
            $immersiveSessionType->getDataDefault() ?? [],
            $sess->getData() ?? []
        );
        // Pull the image before creating the container
        $image = $_ENV['IMMERSIVE_SESSIONS_DOCKER_HUB_IMAGE'] ??
            'docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server';
        $tag = $_ENV['IMMERSIVE_SESSIONS_DOCKER_HUB_TAG'] ?? ($_ENV['APP_ENV'] == 'dev' ? 'dev' : 'latest');
        $this->dockerApiCall($dockerApi, 'POST', '/images/create', [
            'query' => [
                'fromImage' => $image,
                'tag' => $tag
            ],
        ]);
        $responseContent = $this->dockerApiCall($dockerApi, 'POST', '/containers/create', [
            'json' => [
               'Image' => $image.':'.$tag,
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
                    'DOCKER=1',
                    'APP_ENV='.($_ENV['APP_ENV'] ?? 'prod'),
                    'HEALTHCHECK_WRITER_MODE='.($_ENV['IMMERSIVE_SESSIONS_HEALTHCHECK_WRITE_MODE'] ?? 0),
                    'MSP_CHALLENGE_SESSION_ID='.$gameSessionId,
                    'MSP_CHALLENGE_API_BASE_URL_FOR_SERVER='.WatchdogCommunicationMessageHandler::getSessionAPIBaseUrl(
                        $gameList,
                        $dockerApi->getAddress() == 'host.docker.internal' ? 'host.docker.internal' : null
                    ),
                    'MSP_CHALLENGE_API_BASE_URL_FOR_CLIENT='.WatchdogCommunicationMessageHandler::getSessionAPIBaseUrl(
                        $gameList
                    ),
                    'IMMERSIVE_SESSION_ID='.$sess->getId(),
                    'IMMERSIVE_SESSION_REGION_COORDS='.json_encode([
                        'region_bottom_left_x' => $sess->getBottomLeftX(),
                        'region_bottom_left_y' => $sess->getBottomLeftY(),
                        'region_top_right_x' => $sess->getTopRightX(),
                        'region_top_right_y' => $sess->getTopRightY()
                    ]),
                    'IMMERSIVE_SESSION_MONTH='.$sess->getMonth(),
                    // like require_username, require_team, gamemaster_pick
                    'IMMERSIVE_SESSION_DATA='.json_encode($data)
                ],
            ],
        ]);

        // Start the container
        $containerId = $responseContent['Id'];
        $this->dockerApiCall($dockerApi, 'POST', "/containers/{$containerId}/start");
        $conn->setDockerContainerID($containerId);
        $sess
            ->setConnection($conn)
            ->setStatus(ImmersiveSessionStatus::RUNNING)
            ->setStatusResponse([
                'message' => 'Container started.',
            ]);
        $em->persist($conn);
        $em->persist($sess);
        $em->flush();
    }

    /**
     * example:
     * [
     *    {
     *        "Id": "7eaeff03021b41c5215615645b3a7bd86a65d667948d5c018b8b0e3d6c95b320",
     *        "Names": [
     *            "/dazzling_perlman"
     *        ],
     *        "Image": "docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server:latest",
     *        "ImageID": "sha256:c150393083ebb89c5c2e10d0f24fdbfc68bcd252ce7223260e7402c99af37069",
     *        "ImageManifestDescriptor": {
     *            "mediaType": "application/vnd.oci.image.manifest.v1+json",
     *            "digest": "sha256:beb6f40957213aca1a5e9b31065290d503519ab9b0e9b5799bbedbffba0d8bc0",
     *            "size": 1821,
     *            "platform": {
     *                "architecture": "amd64",
     *                "os": "linux"
     *            }
     *        },
     *        "Command": "/app/ImmersiveTwins-Unity",
     *        "Created": 1758832490,
     *        "Ports": [],
     *        "Labels": {
     *            "org.opencontainers.image.ref.name": "ubuntu",
     *            "org.opencontainers.image.version": "24.04"
     *        },
     *        "State": "running",
     *        "Status": "Up 35 minutes",
     *        "HostConfig": {
     *            "NetworkMode": "host"
     *        },
     *        "NetworkSettings": {
     *            "Networks": {
     *                "host": {
     *                    "IPAMConfig": null,
     *                    "Links": null,
     *                    "Aliases": null,
     *                    "MacAddress": "",
     *                    "DriverOpts": null,
     *                    "GwPriority": 0,
     *                    "NetworkID": "6e1640be17c4e023ea8021aa7ed57ef2e35e2d9d106a56b3628f440aa1b62de6",
     *                    "EndpointID": "fcbcb1830fdffe260f55a5bea8f362ab7b43766fb49c9dd7f7ab0ad21a6880c4",
     *                    "Gateway": "",
     *                    "IPAddress": "",
     *                    "IPPrefixLen": 0,
     *                    "IPv6Gateway": "",
     *                    "GlobalIPv6Address": "",
     *                    "GlobalIPv6PrefixLen": 0,
     *                    "DNSNames": null
     *                }
     *            }
     *        },
     *        "Mounts": []
     *    }
     *]
     *
     *
     * State values: "created" "running" "paused" "restarting" "exited" "removing" "dead"
     *
     * @return array{array{Id: string, State: string, Ports: array{PublicPort: int, PrivatePort: int, Type: string}}}
     * @throws Exception
     */
    public function listImmersiveSessionContainers(DockerApi $dockerApi): array
    {
        $image = $_ENV['IMMERSIVE_SESSIONS_DOCKER_HUB_IMAGE'] ??
            'docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server';
        return collect($this->dockerApiCall($dockerApi, 'GET', '/containers/json', [
            'query' => [
                'all' => true,
                'filters' => json_encode([
                    'ancestor' => [$image, "$image:dev"]
                ])
            ]
        ]) ?? [])->map(fn($c) => collect($c)->only(['Id', 'State'])->all())->all();
    }

    /**
     * {
     *     "Id": "a91b9cc43888e232055b3cbbaae5b86e906f69aab3c33fcb7d66e21d229cf0ad",
     *     "Created": "2025-09-25T20:37:52.067876781Z",
     *     "State": {
     *         "Status": "running",
     *         "Running": true,
     *         "Paused": false,
     *         "Restarting": false,
     *         "OOMKilled": false,
     *         "Dead": false,
     *         "Pid": 353,
     *         "ExitCode": 0,
     *         "Error": "",
     *         "StartedAt": "2025-09-25T20:37:52.252669453Z",
     *         "FinishedAt": "0001-01-01T00:00:00Z",
     *         "Health": {
     *             "Status": "healthy",
     *             "FailingStreak": 0,
     *             "Log": [
     *                 {
     *                     "Start": "2025-09-25T21:52:12.052694621Z",
     *                     "End": "2025-09-25T21:52:12.113157277Z",
     *                     "ExitCode": 0,
     *                     "Output": "....."
     *                 }
     *             ]
     *         }
     *     },
     *     "Config": {
     *         "Env": [
     *             "URL_WEB_SERVER_HOST=host.docker.internal"
     *         ],
     *
     * Health Status is one of none, starting, healthy or unhealthy
     *    "none" Indicates there is no healthcheck
     *    "starting" Starting indicates that the container is not yet ready
     *    "healthy" Healthy indicates that the container is running correctly
     *    "unhealthy" Unhealthy indicates that the container has a problem
     *
     * @return array{
     *  Id: string,
     *  State: array{
     *     Status: string,
     *     Health?: array{
     *       Status: string,
     *       FailingStreak: int,
     *       Log: array{array{Start: string, End: string, ExitCode: int, Output: string}}
     *    }
     *  },
     *  Config: array{Env: array<string>
     *   }
     * }
     * @throws Exception
     */
    public function inspectImmersiveSessionContainer(DockerApi $dockerApi, string $dockerContainerId): array
    {
        $inspectData = collect(
            $this->dockerApiCall($dockerApi, 'GET', "/containers/{$dockerContainerId}/json") ?? []
        )->all();
        $state = collect($inspectData['State'])->only(['Status', 'Health'])->all();
        $state['Health']['Log'] = collect($state['Health']['Log'] ?? [])->take(-1)->values()->all();
        return [
            'Id' => $inspectData['Id'],
            'State' => $state,
            'Config' => collect($inspectData['Config'])->only(['Env'])->all()
        ];
    }

    /**
     * @throws Exception
     */
    public function removeImmersiveSessionContainer(DockerApi $dockerApi, string $dockerContainerId): void
    {
        $this->dockerApiCall($dockerApi, 'POST', "/containers/{$dockerContainerId}/stop");
        $this->dockerApiCall($dockerApi, 'DELETE', "/containers/{$dockerContainerId}");
    }

    /**
     * @throws Exception
     */
    public function removeImmersiveSessionConnection(ImmersiveSession $sess, int $gameSessionId): void
    {
        $conn = $sess->getConnection();
        if ($conn === null) {
            throw new Exception('No connection found for this session.');
        }
        $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
        $em->remove($conn);
        $sess
            ->setConnection(null)
            ->setStatus(ImmersiveSessionStatus::STOPPED);
        $em->persist($sess);
        $em->flush();
    }

    /**
     * @throws Exception
     */
    private function dockerApiCall(DockerApi $dockerApi, string $method, string $path, array $options = []): ?array
    {
        // Build the query string
        $queryString = http_build_query($options['query'] ?? []);
        $fullUrl = $dockerApi->createUrl() . $path . ($queryString ? '?' . $queryString : '');

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
