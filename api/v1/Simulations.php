<?php

namespace App\Domain\API\v1;

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Security\BearerTokenValidator;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;

class Simulations extends Base implements JWTUserInterface
{
    private const ALLOWED = array(
        "GetConfiguredSimulationTypes",
    );

    const POSSIBLE_SIMULATIONS = array("MEL", "CEL", "SEL", "REL");

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Simulations
     * @throws Exception
     * @api {POST} /Simulations/GetConfiguredSimulationTypes Get Configured Simulation Types
     * @apiDescription Get Configured Simulation Types (e.g. ["MEL", "SEL", "CEL"])
     * @apiSuccess {array} Returns the type name of the simulations present in the current configuration.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetConfiguredSimulationTypes(): array
    {
        $result = array();
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $config = $game->GetGameConfigValues();
        foreach (self::POSSIBLE_SIMULATIONS as $possibleSim) {
            if (array_key_exists($possibleSim, $config) && is_array($config[$possibleSim])) {
                $versionString = "Latest";
                if (array_key_exists("force_version", $config[$possibleSim])) {
                    $versionString = $config[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return $result;
    }

    /**
     * @apiGroup Simulations
     * @throws Exception
     * @api {POST} /Simulations/GetWatchdogTokenForServer Get Watchdog Token ForServer
     * @apiDescription Get the watchdog token for the current server. Used for setting up debug bridge in simulations.
     * @apiSuccess {array} with watchdog_token key and value
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetTokensForWatchdog(): array
    {
        /*$token = null;
        $data = $this->getDatabase()->query("SELECT game_session_watchdog_token FROM game_session LIMIT 0,1");
        if (count($data) > 0) {
            $token = $data[0]["game_session_watchdog_token"];
        }
        return array("watchdog_token" => $token);*/
        $user = new User();
        $user->setUserId(999999);
        $user->setUsername('Watchdog_'.uniqid());
        $jsonResponse = SymfonyToLegacyHelper::getInstance()->getAuthenticationSuccessHandler()
            ->handleAuthenticationSuccess($user);
        return json_decode($jsonResponse->getContent(), true);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CheckAccess(): array
    {
        $bearerToken = SymfonyToLegacyHelper::getInstance()->getRequest()->headers->get('Authorization');
        $validator = (new BearerTokenValidator())->setTokenFromHeader($bearerToken);
        $timeRemaining = (int) $validator->getClaims()->get('exp')->format('U') - time();
        if ($timeRemaining > 900) {
            $result = 'Valid';
        } else {
            $result = 'UpForRenewal';
        }
        // returning 'Expired' does not make sense, because in that case you wouldn't get here
        return ['status' => $result, 'time_remaining' => $timeRemaining];
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): ?int
    {
        return $this->getUserId();
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return 999999;
    }

    public function getUsername(): ?string
    {
        return 'Simulation Watchdog';
    }

    public function setUsername(?string $user_name): self
    {
        return $this;
    }

    public static function createFromPayload($username, array $payload): Simulations
    {
        return new self;
    }

    public function getPassword(): string|null
    {
        // irrelevant, but required function
        return null;
    }

    public function getSalt(): string|null
    {
        // irrelevant, but required function
        return null;
    }

    public function eraseCredentials(): void
    {
        // irrelevant, but required function
    }
}
