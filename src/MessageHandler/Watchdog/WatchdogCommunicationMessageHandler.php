<?php

namespace App\MessageHandler\Watchdog;

use App\Domain\API\v1\Simulation;
use App\Domain\API\v1\User;
use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Message\Watchdog\Message\GameMonthChangedMessage;
use App\Message\Watchdog\Message\GameStateChangedMessage;
use App\Message\Watchdog\Message\WatchdogPingMessage;
use App\Message\Watchdog\Token;
use App\MessageHandler\GameList\SessionLogHandlerBase;
use App\Entity\SessionAPI\EventLog;
use App\Entity\SessionAPI\Simulation as SimulationEntity;
use App\Entity\SessionAPI\Watchdog;
use App\VersionsProvider;
use DateTime;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class WatchdogCommunicationMessageHandler extends SessionLogHandlerBase
{
    private ConsoleOutput $output;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly HttpClientInterface $client,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly VersionsProvider $provider,
        LoggerInterface $gameSessionLogger
    ) {
        parent::__construct($gameSessionLogger);
        $this->output = new ConsoleOutput();
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    public function __invoke(
        GameMonthChangedMessage|GameStateChangedMessage|WatchdogPingMessage $message
    ): void {
        $this->setGameSessionId($message->getGameSessionId());
        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());

        // instead of using $message->getWatchdog() directly, we need to fetch it through doctrine,
        //   such that the entity is loaded into the the current persistence context
        if (null === $watchdog = $em->find(Watchdog::class, $message->getWatchdogId())) {
            $this->warning('Watchdog not found. Id: '.$message->getWatchdogId());
            return;
        }

        if (null === $watchdog->getGameWatchdogServer()) {
            $em->persist($this->log(
                'no server assigned. Watchdog was removed.',
                EventLogSeverity::ERROR,
                $watchdog
            ));
            $em->remove($watchdog);
            $em->flush();
            return;
        }

        try {
            switch (get_class($message)) {
                case GameMonthChangedMessage::class:
                    $this->requestWatchdog($em, $watchdog, '/Watchdog/SetMonth', [
                        'game_session_token' => (string) $watchdog->getToken(),
                        'month' => $message->getMonth()
                    ]);
                    break;
                case GameStateChangedMessage::class:
                    $this->requestWatchdogUpdateState($message, $watchdog, $em);
                    break;
                case WatchdogPingMessage::class:
                    // fail-safe: no need to ping the internal watchdog
                    if ($watchdog->getServerId() != Watchdog::getInternalServerId()) {
                        $this->requestWatchdog($em, $watchdog, '/Watchdog/Ping', [
                            'game_session_token' => (string)$watchdog->getToken()
                        ]);
                    }
                    break;
                default:
                    throw new Exception('Unknown message type');
            }
        } catch (Exception $e) {
            $em->flush();
            throw $e;
        }

        $em->flush();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws Exception
     */
    private function requestWatchdogUpdateState(
        GameStateChangedMessage $message,
        Watchdog $watchdog,
        EntityManagerInterface $em
    ): void {
        if (null === $gameList = $this->getGameList($message->getGameSessionId())) {
            $this->warning('Game list not found. Id: '.$message->getGameSessionId());
            return;
        }
        $tokens = $this->getTokensForWatchdog($message->getGameSessionId(), $watchdog);
        $apiAccessToken = new Token($tokens['token'], new DateTime('+1 hour'));
        $apiAccessRenewToken = new Token(
            $tokens['api_refresh_token'],
            DateTime::createFromFormat('U', $tokens['exp'])
        );
        $requiredSimulations = collect($watchdog->getSimulations()->toArray())
            ->mapWithKeys(fn(SimulationEntity $sim) => [$sim->getName() => $sim->getVersion()])
            ->toArray();
        $postValues = [
            'game_session_api' => self::getSessionAPIBaseUrl(
                $gameList,
                $watchdog->getServerId() == Watchdog::getInternalServerId() ?
                    ($_ENV['MITMPROXY_PORT'] ? 'mitmproxy' : 'php') : null,
                $watchdog->getServerId() == Watchdog::getInternalServerId() ? ($_ENV['MITMPROXY_PORT'] ?? 80) : null,
                $watchdog->getServerId() == Watchdog::getInternalServerId() ? 'http' : null
            ),
            'game_session_token' => (string)$watchdog->getToken(),
            'game_state' => $message->getGameState()->__toString(),
            'required_simulations' => json_encode($requiredSimulations, JSON_FORCE_OBJECT),
            'api_access_token' => json_encode([
                'token' => $apiAccessToken->getToken(),
                'valid_until' => $apiAccessToken->getValidUntil()->format('Y-m-d H:i:s')
            ]),
            'api_access_renew_token' => json_encode([
                'token' => $apiAccessRenewToken->getToken(),
                'valid_until' => $apiAccessRenewToken->getValidUntil()->format('Y-m-d H:i:s')
            ]),
            'month' => $message->getMonth()
        ];
        if (// only add game_info if not the internal watchdog. This is because the internal watchdog does not handle it
            $watchdog->getServerId() != Watchdog::getInternalServerId()
        ) {
            $postValues['game_session_info'] = $this->getSessionInfo($gameList);
        }
        $this->requestWatchdog($em, $watchdog, '/Watchdog/UpdateState', $postValues);
    }

    /**
     * @throws Exception
     */
    private function getTokensForWatchdog(int $sessionId, Watchdog $watchdog): array
    {
        $user = new User();
        $user->setGameSessionId($sessionId);
        $user->setUserId((int)('999999'.$watchdog->getId()));
        $user->setUsername('Watchdog_'.$watchdog->getGameWatchdogServer()->getServerId()->toRfc4122());
        $jsonResponse = $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
        return json_decode($jsonResponse->getContent(), true);
    }

    /**
     * @throws Exception
     */
    private function checkResponseSuccess(
        Watchdog $watchdog,
        EntityManagerInterface $em,
        string $uri,
        array $context,
        array $decodedResponse
    ): void {
        if (($decodedResponse["success"] ?? 0) == 1) {
            $this->info(
                sprintf(
                    'Watchdog %s: responded with success on requesting %s: %s.',
                    $watchdog->getServerId()->toRfc4122(),
                    $uri,
                    json_encode($context)
                )
            );
            return; // success!
        }
        $errorMsg = sprintf(
            'Watchdog responded with failure on requesting %s: %s. Error message: "%s".',
            $uri,
            json_encode($context),
            ($decodedResponse["message"] ?? '') ?: 'No message'
        );
        $em->persist($this->log(
            $errorMsg,
            EventLogSeverity::ERROR,
            $watchdog
        ));
        throw new Exception($errorMsg);
    }

    private function log(
        string $message,
        EventLogSeverity $severity,
        ?Watchdog $w = null,
        ?string $stackTrace = null
    ): EventLog {
        $source = self::class;
        if (getenv('DOCKER') !== false && // only in docker
            false !== $processName = exec('supervisorctl status | grep '.getmypid().' | awk \'{print $1}\'')) {
            $source .= '@'.$processName;
        }
        $eventLog = Simulation::createEventLogForWatchdog($message, $severity, $w, $stackTrace)
            ->setSource($source);
        $message = sprintf('Watchdog %s: %s', $w->getServerId()->toRfc4122(), $message);
        switch ($severity) {
            case EventLogSeverity::WARNING:
                $this->warning($message);
                break;
            default: // EventLogSeverity::ERROR, EventLogSeverity::FATAL
                $this->error($message);
                break;
        }
        return $eventLog;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws Exception
     */
    public function requestWatchdog(
        EntityManagerInterface $em,
        Watchdog $watchdog,
        string $uri,
        array $postValues
    ): void {
        // output to console
        $this->output->writeln(
            sprintf(
                'Requesting %s on Watchdog %s with values %s',
                $uri,
                $watchdog->getServerId()->toRfc4122(),
                json_encode($postValues)
            )
        );
        try {
            $options = [
                'json' => $postValues,
                'timeout' => $_ENV['WATCHDOG_RESPONSE_TIMEOUT_SEC'] ?? 20
            ];
            if (null !== ($_ENV['WATCHDOG_PROXY_URL'] ?? null)) {
                $options['proxy'] = $_ENV['WATCHDOG_PROXY_URL'];
            }
            $response = $this->client->request(
                "POST",
                $watchdog->getGameWatchdogServer()->createUrl().$uri,
                $options
            );
            $decodedResponse = json_decode(
                json: $response->getContent(),
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (TransportExceptionInterface $e) { // response timeout exception
            $em->persist($watchdog->setStatus(WatchdogStatus::UNRESPONSIVE));
            $em->persist($this->log($e->getMessage(), EventLogSeverity::ERROR, $watchdog, $e->getTraceAsString()));
            throw $e; // Re-throw the exception to trigger the retry mechanism
        } catch (JsonException |
            ServerExceptionInterface | // 5xx errors
            RedirectionExceptionInterface | // 3xx errors max redirects reached
            ClientExceptionInterface $e // 4xx errors
        ) {
            if ($e->getCode() == Response::HTTP_METHOD_NOT_ALLOWED) {
                // the watchdog does not want to join this session
                $em->persist($this->log(
                    'Watchdog does not want to join this session.',
                    EventLogSeverity::WARNING,
                    $watchdog
                ));
            }
            if ($e->getCode() == Response::HTTP_BAD_GATEWAY) {
                $em->persist($watchdog->setStatus(WatchdogStatus::UNRESPONSIVE));
            }
            $em->persist($this->log($e->getMessage(), EventLogSeverity::ERROR, $watchdog, $e->getTraceAsString()));
            throw $e; // Re-throw the exception to trigger the retry mechanism
        }
        $this->checkResponseSuccess(
            $watchdog,
            $em,
            $uri,
            array_filter($postValues, fn($k) => in_array($k, [
                'game_session_token',
                'game_state',
                'required_simulations',
                'month'
            ]), ARRAY_FILTER_USE_KEY),
            $decodedResponse
        );
    }

    /**
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function getGameList(int $gameSessionId): ?GameList
    {
        $qb = ConnectionManager::getInstance()
            ->getServerManagerEntityManager()
            ->getRepository(GameList::class)
            ->createQueryBuilder('g');
        return $qb
            ->innerJoin('g.gameServer', 'gse')
            ->innerJoin('g.gameWatchdogServer', 'gws')
            ->leftJoin('g.gameConfigVersion', 'gcv')
            ->leftJoin('gcv.gameConfigFile', 'gcf')
            ->leftJoin('g.gameGeoServer', 'ggs')
            ->leftJoin('g.gameSave', 'gsa')
            ->where($qb->expr()->eq('g.id', ':id'))
            ->setParameter('id', $gameSessionId)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
    }

    /**
     * @param GameList $gameList
     * @return array
     */
    public function getSessionInfo(GameList $gameList): array
    {
        $configMetadata = $gameList->getGameConfigVersion()->getGameConfigComplete()['metadata'];
        $gameSessionInfo['id'] = $gameList->getId();
        $gameSessionInfo['name'] = $gameList->getName();
        $gameSessionInfo['region'] = $gameList->getGameConfigVersion()->getRegion();
        $gameSessionInfo['config_version'] = $gameList->getGameConfigVersion()->getVersion();
        $gameSessionInfo['config_version_message'] = $gameList->getGameConfigVersion()->getVersionMessage();
        $gameSessionInfo['config_file_name'] = $gameList->getGameConfigVersion()->getGameConfigFile()->getFilename();
        $gameSessionInfo['config_file_description'] =
            $gameList->getGameConfigVersion()->getGameConfigFile()->getDescription();
        $gameSessionInfo['config_file_metadata_date_modified'] = $configMetadata['date_modified'];
        $gameSessionInfo['config_file_metadata_model_hash'] = $configMetadata['data_model_hash'];
        $gameSessionInfo['config_file_metadata_editor_version'] = $configMetadata['editor_version'];
        $gameSessionInfo['config_file_metadata_config_version'] = $configMetadata['config_version'];
        $gameSessionInfo['server_version'] = $this->provider->getVersion();
        return $gameSessionInfo;
    }

    /**
     * used to communicate "game_session_api" URL to the watchdog
     *
     * @throws Exception
     */
    public static function getSessionAPIBaseUrl(
        GameList $gameList,
        ?string $address = null,
        ?int $port = null,
        ?string $scheme = null
    ): string {
        $scheme ??= ($_ENV['URL_WEB_SERVER_SCHEME'] ?? 'http');
        $protocol = str_replace('://', '', $scheme).'://';
        $address ??= ($_ENV['URL_WEB_SERVER_HOST'] ?? null) ?: $gameList->getGameServer()->getAddress() ??
            gethostname();
        $port ??= ($_ENV['URL_WEB_SERVER_PORT'] ?? 80);
        return $protocol.$address.':'.$port.'/'.$gameList->getId().'/';
    }
}
