<?php

namespace App\Domain\Communicator;

use App\Domain\API\v1\User;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\VersionsProvider;
use Doctrine\DBAL\Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
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
        protected HttpClientInterface $client,
        private readonly VersionsProvider $versionsProvider,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function changeState(
        GameList $gameList,
        string $newWatchdogState
    ): array {
        $this->gameList = $gameList;
        $this->ensureWatchDogAlive();
        $tokens = $this->getAPITokens();
        $this->lastCompleteURLCalled = $this->getWatchdogUrl();
        $postValues = [
            'game_session_api' => $this->getSessionAPIBaseUrl(),
            'game_session_token' => ConnectionManager::getInstance()
                ->getCachedGameSessionDbConnection($this->gameList->getId())->createQueryBuilder()
                ->select('game_session_watchdog_token')->from('game_session')->fetchOne(), // legacy
            'game_state' => strtoupper($newWatchdogState),
            'required_simulations' => $this->getRequiredSimulations(),
            'api_access_token' => json_encode([
                'token' => $tokens['token'],
                'valid_until' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s')
            ]),
            'api_access_renew_token' => json_encode([
                'token' => $tokens['api_refresh_token'],
                'valid_until' => \DateTime::createFromFormat('U', $tokens['exp'])->format('Y-m-d H:i:s')
            ])
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
        //todo:
        // 1. *only* if you're running the server on Windows...
        // 2. ...and the Watchdog is not responding quick enough (meaning it's not alive)...
        // 3. ...and that Watchdog is meant to run locally (so not at some completely different address),
        //    so: $this->gameList->getGameWatchdogServer()->getId() === 1;
        // 4. then start up locally the Watchdog through the command, and do that only once.
        //    I really think we shouldn't do this through the async websocket process with multiple attempts.
        //    Instead, just call the MSW.exe process, a bit like below, but through a method in this class and
        //    without SymfonyToLegacyHelper. And add a sleep(2) to it at the end if you want to give it a little time.
        /*Game::StartSimulationExe([
            'exe' => 'MSW.exe',
            'working_directory' => SymfonyToLegacyHelper::getInstance()->getProjectDir().'/'.(
                    $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'] ?? 'simulations/.NETFramework/MSW/'
                )
        ]);*/
        /*Database::execInBackground(
            'start cmd.exe @cmd /c "'.$workingDirectory.'start '.$params["exe"].' '.$args.'"'
        );*/
    }

    private function getWatchdogUrl(): string
    {
        $address = $_ENV['WATCHDOG_ADDRESS'] ?? $this->gameList->getGameWatchdogServer()->getAddress();
        /** @noinspection HttpUrlsUsage */
        $address = 'http://'.preg_replace('~^https?://~', '', $address);
        return $address.':' . ($_ENV['WATCHDOG_PORT'] ?? self::DEFAULT_WATCHDOG_PORT) . '/Watchdog/UpdateState';
    }

    private function getSessionAPIBaseUrl(): string
    {
        if (getenv('DOCKER') !== false) {
            return "http://caddy:80/{$this->gameList->getId()}/";
        }
        /** @noinspection HttpUrlsUsage */
        $protocol = $_ENV['URL_WEB_SERVER_SCHEME'] ?? 'http://';
        $address = $_ENV['URL_WEB_SERVER_HOST'] ?? $this->gameList->getGameServer()->getAddress();
        $port = $_ENV['URL_WEB_SERVER_PORT'] ?? 80;
        return "{$protocol}{$address}:{$port}/{$this->gameList->getId()}/";
    }
    private function getRequiredSimulations(): string
    {
        $result = [];
        $possibleSims = $this->versionsProvider->getComponentsVersions();
        $config = $this->gameList->getGameConfigVersion()->getGameConfigComplete()['datamodel'];
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
