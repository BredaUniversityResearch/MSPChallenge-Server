<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\DockerApi;
use App\Message\Docker\InspectDockerConnectionsMessage;
use DateTime;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\Browser as ReactBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:monitor-docker-api-events',
    description: 'Monitor Docker API events, and trigger message handlers accordingly',
)]
class MonitorDockerApiEventsCommand extends Command
{
    private const INSPECT_COOLDOWN_SECONDS = 15;
    private const EVENTS_FILTERS_DEFAULTS = [
        'type' => ['container'],
        'event' => [
            'create',       // container created → STARTING
            'start',        // container started → RUNNING (then check health)
            'stop',         // container stopped → STOPPED
            'die',          // container exited → STOPPED
            'restart',      // container restarted → STARTING → RUNNING
            'pause',        // container paused → STOPPED
            'unpause',      // container unpaused → RUNNING
            'kill',         // container killed → STOPPED / UNRESPONSIVE
            'oom',          // out-of-memory → UNRESPONSIVE
            'destroy',      // container removed → REMOVED
            'health_status' // health changed → RUNNING / STARTING / UNRESPONSIVE
        ],
    ];

    /** @var DockerApi[] $dockerApis */
    private array $dockerApis = [];
    private LoopInterface $loop;

    /**
     * @var array{
     *     lastMessageSentAt: ?DateTime,
     *     pendingTimer: ?TimerInterface
     * } $inspectDockerConnectionsState
     */
    private array $inspectDockerConnectionsState = [
        'lastMessageSentAt' => null,
        'pendingTimer' => null
    ];

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $dockerMonitorLogger
    ) {
        parent::__construct();
    }

    private function createConnectionFunction(
        DockerApi $dockerApi
    ): \Closure {
        return function () use ($dockerApi) {
            if ($dockerApi->getInvalidated()) {
                $this->outputDockerApiMessage(
                    $dockerApi->getId(),
                    'comment',
                    'Docker API connection invalidated. Aborting connection.'
                );
                unset($this->dockerApis[$dockerApi->getId()]);
                return;
            }
            $this->dockerApis[$dockerApi->getId()] = $dockerApi;
            $image = $_ENV['IMMERSIVE_SESSIONS_DOCKER_HUB_IMAGE'] ??
                'docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server';
            $filters = array_merge(
                self::EVENTS_FILTERS_DEFAULTS,
                [
                    'image' => [$image]
                ]
            );
            $queryParams = ['filters' => json_encode($filters)];
            if ($dockerApi->getLastDockerEventAt() !== null) {
                $queryParams['since'] = $dockerApi->getLastDockerEventAt()->getTimestamp();
            }
            $eventsUrl = $dockerApi->createUrl().'/events?'.http_build_query($queryParams);
            $browser = new ReactBrowser($this->loop);
            $this->dockerMonitorLogger->info('Connecting to Docker events stream at '.$eventsUrl);
            $browser->requestStreaming('GET', $eventsUrl)->then(
                function (ResponseInterface $response) use ($dockerApi) {
                    $body = $response->getBody();
                    assert($body instanceof \Psr\Http\Message\StreamInterface);
                    assert($body instanceof \React\Stream\ReadableStreamInterface);
                    $buffer = '';
                    $body->on('data', $this->createStreamDataReceivedFunction($dockerApi, $buffer));
                    $body->on('close', function () use ($dockerApi) {
                        $this->outputDockerApiMessage(
                            $dockerApi->getId(),
                            'comment',
                            'Docker events stream ended. Reconnecting...'
                        );
                        $this->loop->futureTick(
                            fn() => $this->createConnectionFunction($dockerApi)()
                        );
                    });
                },
                function (Exception $e) use ($dockerApi) {
                    $this->outputDockerApiMessage(
                        $dockerApi->getId(),
                        'error',
                        'Failed to connect to Docker events endpoint: '.$e->getMessage()
                    );
                    $this->loop->addTimer(10, fn() => $this->createConnectionFunction($dockerApi)());
                }
            );
        };
    }

    private function createStreamDataReceivedFunction(DockerApi $dockerApi, string &$buffer): \Closure
    {
        return function (string $chunk) use (&$buffer, $dockerApi) {
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                if (trim($line) === '') {
                    continue;
                }
                /** @var array{id: string, Action: string, time: string} $event */
                try {
                    $event = collect(json_decode($line, true, flags: JSON_THROW_ON_ERROR))
                        ->only(['id', 'Action', 'time', 'timeNano'])->all();
                } catch (\Exception $e) {
                    $this->outputDockerApiMessage(
                        $dockerApi->getId(),
                        'error',
                        'Failed to decode event line: '.$line.' Error: '.$e->getMessage()
                    );
                    continue;
                }
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->outputDockerApiMessage(
                        $dockerApi->getId(),
                        'info',
                        'Docker event: '.json_encode($event)
                    );
                    if (isset($event['time'])) {
                        $dockerApi->setLastDockerEventAt((new DateTime())->setTimestamp($event['time']));
                    }
                    // Throttling logic: handle event with cooldown
                    $this->triggerInspectDockerConnections();
                } else {
                    $this->outputDockerApiMessage(
                        $dockerApi->getId(),
                        'error',
                        'Failed to decode event: '.json_encode($event)
                    );
                }
            }
        };
    }

    private function triggerInspectDockerConnections(): void
    {
        $state = &$this->inspectDockerConnectionsState;
        $now = new \DateTime();
        // Check if we can inspect immediately
        if ($state['lastMessageSentAt'] === null ||
            ($now->getTimestamp() - $state['lastMessageSentAt']->getTimestamp()) >=
            self::INSPECT_COOLDOWN_SECONDS
        ) {
            $this->messageBus->dispatch(new InspectDockerConnectionsMessage());
            $state['lastMessageSentAt'] = $now;
            // If a timer was pending, cancel it
            if ($state['pendingTimer']) {
                $this->loop->cancelTimer($state['pendingTimer']);
                $state['pendingTimer'] = null;
            }
            return;
        }

        // There is a cooldown in effect, no need for further action if a timer is already pending
        if ($state['pendingTimer']) {
            return;
        }

        // Schedule a timer to send the message after the cooldown period
        $delay = self::INSPECT_COOLDOWN_SECONDS - ($now->getTimestamp() - $state['lastMessageSentAt']->getTimestamp());
        $state['pendingTimer'] = $this->loop->addTimer($delay, function () {
            $this->messageBus->dispatch(new InspectDockerConnectionsMessage());
            $this->inspectDockerConnectionsState['lastMessageSentAt'] = new \DateTime();
            $this->inspectDockerConnectionsState['pendingTimer'] = null;
        });
    }

    /**
     * Periodically refreshes DockerApis, invalidates outdated/removed, and starts new connections
     */
    private function startDockerApiRefreshLoop(): void
    {
        $this->loop->addPeriodicTimer(60, function () {
            $latestDockerApis = $this->getLatestDockerApis();
            $dockerApis = $this->dockerApis; // Copy to avoid modification during iteration
            foreach ($dockerApis as $id => $dockerApi) {
                // Invalidate connections for removed Docker api's
                if (!isset($latestDockerApis[$id])) {
                    $dockerApi->setInvalidated();
                } else {
                    // update the object instance in case of changes
                    $dockerApi
                        ->setAddress($latestDockerApis[$id]->getAddress())
                        ->setPort($latestDockerApis[$id]->getPort())
                        ->setScheme($latestDockerApis[$id]->getScheme());
                }
            }
            // Start new connections for new Docker api's
            foreach ($latestDockerApis as $id => $api) {
                if (!isset($dockerApis[$id])) {
                    $this->createConnectionFunction($api)();
                }
            }
        });
    }

    private function formatDockerApiId(?int $dockerApiId): string
    {
        return '#'.str_pad($dockerApiId ?? '0', 6, '_', STR_PAD_LEFT);
    }
    private function outputDockerApiMessage(int $dockerApiId, string $type, string $message): void
    {
        $this->dockerMonitorLogger->log(
            $type,
            "[".$this->formatDockerApiId($dockerApiId)."] $message",
            ['dockerApiId' => $dockerApiId]
        );
    }

    /**
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loop = Loop::get();
        $this->dockerMonitorLogger->info('Connecting to Docker events streams...');
        if (function_exists('pcntl_signal')) {
            \pcntl_signal(SIGTERM, function () {
                $this->loop->stop();
            });
        }
        $dockerApis = $this->getLatestDockerApis();
        foreach ($dockerApis as $dockerApi) {
            $this->createConnectionFunction($dockerApi)();
        }
        // Add periodic timer to trigger inspect every minute
        $this->loop->addPeriodicTimer(60, function () {
            $this->triggerInspectDockerConnections();
        });
        // Add periodic timer to refresh DockerApis and update connections
        $this->startDockerApiRefreshLoop();
        $this->loop->run();
        $this->dockerMonitorLogger->info('MonitorDockerApiEventsCommand stopped.');
        return Command::SUCCESS;
    }

    /**
     * @return DockerApi[]
     *
     * Fetches the latest DockerApi entities from the database
     * @throws Exception
     */
    private function getLatestDockerApis(): array
    {
        $em = $this->connectionManager->getServerManagerEntityManager();
        $em->clear(); // Detach all entities
        return collect($this->connectionManager->getServerManagerEntityManager()->getRepository(DockerApi::class)
            ->findAll())->keyBy(fn(DockerApi $api) => $api->getId())->all();
    }
}
