<?php

namespace App\Domain\Communicator;

use App\Domain\API\v1\User;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\Game;
use App\Entity\ServerManager\GameList;
use App\VersionsProvider;
use Doctrine\DBAL\Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WatchdogCommunicator extends AbstractCommunicator
{
    private const DEFAULT_WATCHDOG_PORT = 45000;
    private GameList $gameList;

    public function __construct(
        HttpClientInterface $client,
        private readonly VersionsProvider $versionsProvider,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly string $projectDir,
        private readonly ConnectionManager $connectionManager
    ) {
        parent::__construct($client);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     * @throws \Exception
     */
    public function changeState(int $sessionId, GameStateValue $newWatchdogState): array
    {
        if ($_ENV['APP_ENV'] === 'test') {
            return [];
        }
        $this->gameList = ConnectionManager::getInstance()->getServerManagerEntityManager()
            ->getRepository(GameList::class)->find($sessionId);
        $this->ensureWatchDogAlive();
        $tokens = $this->getAPITokens();
        $this->lastCompleteURLCalled = $this->getWatchdogUrl();
        $postValues = [
            'game_session_api' => $this->getSessionAPIBaseUrl(),
            'game_session_token' => ConnectionManager::getInstance()
                ->getCachedGameSessionDbConnection($this->gameList->getId())->createQueryBuilder()
                ->select('game_session_watchdog_token')->from('game_session')->fetchOne(),
            'game_state' => strtoupper($newWatchdogState),
            'required_simulations' => $this->getRequiredSimulations(),
            'api_access_token' => json_encode([
                'token' => $tokens['token'],
                'valid_until' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s')
            ]),
            'api_access_renew_token' => json_encode([
                'token' => $tokens['api_refresh_token'],
                'valid_until' => \DateTime::createFromFormat('U', $tokens['exp'])->format('Y-m-d H:i:s')
            ]),
            'month' => ConnectionManager::getInstance()->getGameSessionEntityManager($sessionId)
                ->getRepository(Game::class)->retrieve()->getGameCurrentmonth()
        ];
        $response = $this->client->request("POST", $this->lastCompleteURLCalled, [
            'body' => http_build_query($postValues)
        ]);
        return $response->toArray();
    }

    private function ensureWatchDogAlive(): void
    {
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
            return 'http://'.($_ENV['WEB_SERVER_HOST'] ?? 'caddy').':'.($_ENV['WEB_SERVER_PORT'] ?? 80).'/'.$sessionId.
                '/';
        }
        /** @noinspection HttpUrlsUsage */
        $protocol = $_ENV['URL_WEB_SERVER_SCHEME'] ?? 'http://';
        $address = ($_ENV['URL_WEB_SERVER_HOST'] ?? null) ?: $this->gameList->getGameServer()->getAddress() ??
            gethostname();
        $port = $_ENV['URL_WEB_SERVER_PORT'] ?? 80;
        return $protocol.$address.':'.$port.'/'.$sessionId.'/';
    }

    /**
     * @throws \Exception
     */
    private function getRequiredSimulations(): string
    {
        $result = [];
        $possibleSims = $this->versionsProvider->getComponentsVersions();
        $em = $this->connectionManager->getGameSessionEntityManager($this->gameList->getId());
        $game = $em->getRepository(Game::class)->retrieve();
        $em->refresh($game); // don't really know why, but without this the postLoad event isn't initiated
        $config = $game->getRunningGameConfigFileContents()['datamodel'];
        foreach ($possibleSims as $possibleSim => $possibleSimVersion) {
            if (array_key_exists($possibleSim, $config) && is_array($config[$possibleSim])) {
                $versionString = $possibleSimVersion;
                if (array_key_exists("force_version", $config[$possibleSim])) {
                    $versionString = $config[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return json_encode($result, JSON_FORCE_OBJECT);
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
