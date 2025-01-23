<?php

namespace App\MessageHandler\Watchdog;

use App\Domain\API\v1\GameSession;
use App\Domain\API\v1\Simulation;
use App\Domain\API\v1\User;
use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Services\ConnectionManager;
use App\Entity\EventLog;
use App\Entity\ServerManager\GameList;
use App\Entity\Simulation as SimulationEntity;
use App\Entity\Watchdog;
use App\Message\Watchdog\GameMonthChangedMessage;
use App\Message\Watchdog\GameStateChangedMessage;
use App\Message\Watchdog\Token;
use App\MessageHandler\GameList\SessionLogHandlerBase;
use App\VersionsProvider;
use DateTime;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
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
use function App\await;

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
        GameMonthChangedMessage|GameStateChangedMessage $message
    ): void {
        $this->setGameSessionId($message->getGameSessionId());
        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());

        $watchdog = $message->getWatchdog();
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
            if ($message instanceof GameMonthChangedMessage) {
                $this->requestWatchdog($em, $message->getWatchdog(), '/Watchdog/SetMonth', [
                    'game_session_token' => $message->getWatchdog()->getToken(),
                    'month' => $message->getMonth()
                ]);
            } else {
                assert($message instanceof GameStateChangedMessage);
                $this->requestWatchdogUpdateState($message, $em);
            }
        } catch (Exception $e) {
            // we still need to flush the log messages on exception
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
        EntityManagerInterface $em
    ): void {
        $tokens = $this->getTokensForWatchdog($message->getGameSessionId());
        $apiAccessToken = new Token($tokens['token'], new DateTime('+1 hour'));
        $apiAccessRenewToken = new Token(
            $tokens['api_refresh_token'],
            DateTime::createFromFormat('U', $tokens['exp'])
        );
        $requiredSimulations = collect($message->getWatchdog()->getSimulations()->toArray())
            ->mapWithKeys(fn(SimulationEntity $sim) => [$sim->getName() => $sim->getVersion()])
            ->toArray();
        $postValues = [
            'game_session_api' => await(GameSession::getSessionAPIBaseUrl($message->getGameSessionId())),
            'game_session_token' => (string)$message->getWatchdog()->getToken(),
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
        if ($message->getGameState() == GameStateValue::SETUP) {
            $postValues['game_session_info'] = $this->getSessionInfo($message);
        }
        $this->requestWatchdog($em, $message->getWatchdog(), '/Watchdog/UpdateState', $postValues);
    }

    /**
     * @throws Exception
     */
    private function getTokensForWatchdog(int $sessionId): array
    {
        $user = new User();
        $user->setGameSessionId($sessionId);
        $user->setUserId(999999);
        $user->setUsername('Watchdog_' . uniqid());
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
        array $decodedResponse,
        int $statusCode
    ): void {
        if (($decodedResponse["success"] ?? 0) == 1) {
            $this->info(
                sprintf(
                    'Watchdog %s: responded with success on requesting %s.',
                    $watchdog->getServerId()->toRfc4122(),
                    $uri
                )
            );
            return; // success!
        }
        if ($statusCode == Response::HTTP_METHOD_NOT_ALLOWED) {
            // the watchdog does not want to join this session
            $em->remove($watchdog);
            $em->persist($this->log(
                'Watchdog does not want to join this session. Watchdog was removed.',
                EventLogSeverity::WARNING,
                $watchdog
            ));
            return;
        }
        $errorMsg = sprintf(
            'Watchdog responded with failure on requesting %s. Error message: "%s".',
            $uri,
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
            $response = $this->client->request(
                "POST",
                $watchdog->getGameWatchdogServer()->createUrl().$uri,
                [
                    'json' => $postValues,
                    'timeout' => 10.0,
                    'proxy' => 'http://host.docker.internal:8888'
                ]
            );
            $decodedResponse = json_decode(
                json: $response->getContent(),
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (TransportExceptionInterface $e) {
            $em->persist($watchdog->setStatus(WatchdogStatus::UNRESPONSIVE));
            $em->persist($this->log($e->getMessage(), EventLogSeverity::ERROR, $watchdog, $e->getTraceAsString()));
            throw $e; // Re-throw the exception to trigger the retry mechanism
        } catch (JsonException |
            ServerExceptionInterface | // 5xx errors
            RedirectionExceptionInterface | // 3xx errors max redirects reached
            ClientExceptionInterface $e // 4xx errors
        ) {
            $em->persist($this->log($e->getMessage(), EventLogSeverity::ERROR, $watchdog, $e->getTraceAsString()));
            throw $e; // Re-throw the exception to trigger the retry mechanism
        }
        $this->checkResponseSuccess($watchdog, $em, $uri, $decodedResponse, $response->getStatusCode());
    }

    /**
     * @param GameStateChangedMessage $message
     * @return array
     * @throws Exception
     */
    public function getSessionInfo(GameStateChangedMessage $message): array
    {
        $qb = ConnectionManager::getInstance()
            ->getServerManagerEntityManager()
            ->getRepository(GameList::class)
            ->createQueryBuilder('g');
        /** @var GameList $gameList */
        $gameList = $qb
            ->innerJoin('g.gameServer', 'gse')
            ->innerJoin('g.gameWatchdogServer', 'gws')
            ->leftJoin('g.gameConfigVersion', 'gcv')
            ->leftJoin('gcv.gameConfigFile', 'gcf')
            ->leftJoin('g.gameGeoServer', 'ggs')
            ->leftJoin('g.gameSave', 'gsa')
            ->where($qb->expr()->eq('g.id', ':id'))
            ->setParameter('id', $message->getGameSessionId())
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
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
}
