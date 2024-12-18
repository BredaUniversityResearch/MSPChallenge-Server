<?php

namespace App\Domain\Communicator;

use App\Domain\API\v1\User;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Entity\Simulation;
use App\Entity\Watchdog;
use App\Message\Watchdog\GameStateChangedMessage;
use App\Message\Watchdog\Token;
use App\VersionsProvider;
use DateTime;
use Doctrine\DBAL\Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function React\Promise\resolve;

class WatchdogCommunicator extends AbstractCommunicator
{
    private const DEFAULT_WATCHDOG_PORT = 45000;
    private GameList $gameList;

    public function __construct(
        HttpClientInterface $client,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly string $projectDir,
        private readonly ConnectionManager $connectionManager,
        private MessageBusInterface $watchdogMessageBus
    ) {
        parent::__construct($client);
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     * @throws \Exception
     */
    public function changeState(
        GameList $gameList,
        GameStateValue $newWatchdogState
    ): void {
        if ($_ENV['APP_ENV'] === 'test') {
            return;
        }
        $this->gameList = $gameList;
        $em = $this->connectionManager->getGameSessionEntityManager($gameList->getId());
        $watchdogRepo = $em->getRepository(Watchdog::class);
        $watchdogs = $watchdogRepo->findAll();
        foreach ($watchdogs as $watchdog) {
            $this->ensureWatchDogAlive($watchdog);
            $tokens = $this->getAPITokens();
            $message = new GameStateChangedMessage();
            $message
                ->setGameSessionId($gameList->getId())
                ->setWatchdog($watchdog)
                ->setGameSessionApi($this->getSessionAPIBaseUrl())
                ->setGameState($newWatchdogState)
                // sets an array keyed by the simulation name with the corresponding version
                ->setRequiredSimulations(
                    collect($watchdog->getSimulations()->toArray())
                        ->mapWithKeys(fn(Simulation $sim) => [$sim->getName() => $sim->getVersion()])
                        ->toArray()
                )
                ->setApiAccessToken(new Token($tokens['token'], new DateTime('+1 hour')))
                ->setApiAccessRenewToken(new Token(
                    $tokens['api_refresh_token'],
                    DateTime::createFromFormat('U', $tokens['exp'])
                ))
                ->setMonth($gameList->getGameCurrentMonth());
            $this->watchdogMessageBus->dispatch($message);
        }
        resolve();
    }

    private function ensureWatchDogAlive(Watchdog $watchdog): void
    {
        if ($watchdog->getServerId() !== Watchdog::getInternalServerId()) {
            return;
        }
        if (getenv('DOCKER') !== false) {
            return;
        }
        if (!str_starts_with(php_uname(), "Windows")) {
            return;
        }
        if ($this->gameList->getGameWatchdogServer()->getId() !== 1) {
            return;
        }
        // A TransportExceptionInterface will be issued if nothing happens for 2.5 seconds
        try {
            // we do not care about the response, we just want to know if the watchdog is alive
            $this->client->request("GET", $this->getWatchdogUrl(), ['timeout' => 2.5]);
            $continue = false;
        } catch (TransportExceptionInterface $e) {
            $continue = true;
        }
        if ($continue) {
            $process = new Process(
                ['MSW.exe', 'APIEndpoint=' . $this->getSessionAPIBaseUrl()],
                $this->projectDir . '/' . (
                    $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'] ?? 'simulations/.NETFramework/MSW/'
                )
            );
            $process->start(); // start asynchronously
        }
        sleep(2);
    }

    private function getWatchdogUrl(): string
    {
        $address = $_ENV['WATCHDOG_ADDRESS'] ?? $this->gameList->getGameWatchdogServer()->getAddress();
        /** @noinspection HttpUrlsUsage */
        $address = 'http://'.preg_replace('~^https?://~', '', $address);
        $port = $_ENV['WATCHDOG_PORT'] ?? self::DEFAULT_WATCHDOG_PORT;
        return "{$address}:{$port}/Watchdog/UpdateState";
    }

    private function getSessionAPIBaseUrl(): string
    {
        $sessionId = $this->gameList->getId();
        if (getenv('DOCKER') !== false) {
            return 'http://'.($_ENV['WEB_SERVER_HOST'] ?? 'php').':'.($_ENV['MITMPROXY_PORT'] ?? 80).'/'.$sessionId.
                '/';
        }
        /** @noinspection HttpUrlsUsage */
        $protocol = ($_ENV['URL_WEB_SERVER_SCHEME'] ?? 'http').'://';
        $address = ($_ENV['URL_WEB_SERVER_HOST'] ?? null) ?: $this->gameList->getGameServer()->getAddress() ??
            gethostname();
        $port = $_ENV['URL_WEB_SERVER_PORT'] ?? 80;
        return $protocol.$address.':'.$port.'/'.$sessionId.'/';
    }

    private function getAPITokens(): array
    {
        $user = new User();
        $user->setGameSessionId($this->gameList->getId());
        $user->setUserId(999999);
        $user->setUsername('Watchdog_' . uniqid());
        $jsonResponse = $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
        return json_decode($jsonResponse->getContent(), true);
    }
}
