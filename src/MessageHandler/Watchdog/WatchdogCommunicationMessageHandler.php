<?php

namespace App\MessageHandler\Watchdog;

use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Services\ConnectionManager;
use App\Entity\EventLog;
use App\Entity\Watchdog;
use App\Message\Watchdog\GameMonthChangedMessage;
use App\Message\Watchdog\GameStateChangedMessage;
use App\Message\Watchdog\WatchdogMessageBase;
use App\Repository\WatchdogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JsonException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class WatchdogCommunicationMessageHandler
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly HttpClientInterface $client
    ) {
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    public function __invoke(GameMonthChangedMessage|GameStateChangedMessage $message): void
    {
        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());

        // find corresponding watchdog entry
        /** @var WatchdogRepository $watchdogRepo */
        $watchdogRepo = $em->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => $message->getWatchdog()->getServerId()])) {
            $em->persist($this->log(
                sprintf(
                    'Could not find watchdog with server id: %s. Cancelling %s handling.',
                    $message->getWatchdog()->getServerId()->toBinary(),
                    GameStateChangedMessage::class
                ),
                EventLogSeverity::ERROR
            ));
            $em->flush();
            return;
        }

        try {
            if ($message instanceof GameMonthChangedMessage) {
                $this->requestWatchdog($message, $em, $watchdog, '/Watchdog/SetMonth', [
                    'game_session_token' => $message->getWatchdog()->getToken(),
                    'month' => $message->getMonth()
                ]);
            } else {
                assert($message instanceof GameStateChangedMessage);
                $this->requestWatchdog($message, $em, $watchdog, '/Watchdog/UpdateState', [
                    'game_session_api' => $message->getGameSessionApi(),
                    'game_session_token' => $message->getWatchdog()->getToken(),
                    'game_state' => $message->getGameState(),
                    'required_simulations' => json_encode($message->getRequiredSimulations(), JSON_FORCE_OBJECT),
                    'api_access_token' => json_encode([
                        'token' => $message->getApiAccessToken()->getToken(),
                        'valid_until' => $message->getApiAccessToken()->getValidUntil()->format('Y-m-d H:i:s')
                    ]),
                    'api_access_renew_token' => json_encode([
                        'token' => $message->getApiAccessRenewToken()->getToken(),
                        'valid_until' => $message->getApiAccessRenewToken()->getValidUntil()->format('Y-m-d H:i:s')
                    ]),
                    'month' => $message->getMonth()
                ]);
            }
        } catch (Exception $e) {
            // we still need to flush the log messages on exception
            $em->flush();
            throw $e;
        }

        $em->flush();
    }

    /**
     * @throws Exception
     */
    private function checkResponseSuccess(
        Watchdog $watchdog,
        EntityManagerInterface $em,
        string $uri,
        array $decodedResponse
    ): void {
        if (($decodedResponse["success"] ?? 0) == 1) {
            return; // success!
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
        $eventLog = new EventLog();
        $eventLog
            ->setSource($source)
            ->setMessage($message)
            ->setSeverity($severity)
            ->setStackTrace($stackTrace);
        if (null !== $w) {
            $eventLog
                ->setReferenceObject(Watchdog::class)
                ->setReferenceId($w->getId());
        }
        return $eventLog;
    }

    private function createWatchdogBaseUrlFromMessage(WatchdogMessageBase $message): string
    {
        $scheme = $message->getWatchdog()->getScheme();
        $port = $_ENV['WATCHDOG_PORT'] ?? $message->getWatchdog()->getPort();
        return "{$scheme}://{$message->getWatchdog()->getAddress()}:{$port}";
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
        WatchdogMessageBase $message,
        EntityManagerInterface $em,
        Watchdog $watchdog,
        string $uri,
        array $postValues
    ): void {
        try {
            $decodedResponse = json_decode(
                json: $this->client->request(
                    "POST",
                    $this->createWatchdogBaseUrlFromMessage($message).$uri,
                    [
                        'body' => http_build_query($postValues),
                        'timeout' => 10.0
                    ]
                )->getContent(),
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
        $this->checkResponseSuccess($watchdog, $em, $uri, $decodedResponse);
    }
}