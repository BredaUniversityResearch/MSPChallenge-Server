<?php

namespace App\Domain\API\v1;

use App\Domain\API\APIHelper;
use App\Domain\Helper\Util;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\Analytics\UserLogOnOffSessionMessage;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Drift\DBAL\Result;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use ServerManager\ServerManager;
use Symfony\Component\Uid\Uuid;
use function App\await;

class User extends Base implements JWTUserInterface
{
    private ?string $user_name;
    private ?int $user_id;

    // @note "Make the constructor final to prevent the class from being extended"
    //  see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    final public function __construct()
    {
    }

    /**
     * @apiGroup User
     * @apiDescription Creates a new session for the desired country id.
     * @throws Exception
     * @api {POST} /user/RequestSession Set State
     * @apiSuccess {json} Returns a json object describing the 'success' state, the 'session_id' generated for the user.
     *   And in case of a failure a 'message' that describes what went wrong.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RequestSession(
        string $build_timestamp,
        int $country_id,
        string $user_name,
        string $country_password = ""
    ): array {
        $response = array();
        $connection = ConnectionManager::getInstance()->getCachedServerManagerDbConnection();
        $this->CheckVersion($build_timestamp);
        $qb = $connection->createQueryBuilder();
        $qb->select('password_admin', 'password_player')
            ->from('game_list')
            ->where($qb->expr()->eq('id', $this->getGameSessionId()));
        $passwords = $connection->executeQuery($qb->getSQL())->fetchAllAssociative();
        $password_admin = $passwords[0]["password_admin"];
        $password_player = $passwords[0]["password_player"];
        if (!parent::isNewPasswordFormat($password_admin) || !parent::isNewPasswordFormat($password_player)) {
            return $this->RequestSessionLegacy($country_id, $country_password, $user_name);
        } else {
            $password_admin = json_decode(base64_decode($password_admin), true);
            $password_player = json_decode(base64_decode($password_player), true);

            // check whether this is an admin, region manager, or player requesting entrance and get the authentication
            //   provider accordingly
            if ($country_id == 1) {
                $provider = $password_admin["admin"]["provider"];
            } elseif ($country_id == 2) {
                $provider = $password_admin["region"]["provider"];
            } else {
                $provider = $password_player["provider"];
            }
            // request authentication
            if ($provider == "local") {
                // simple locally stored password authentication
                if ($country_id == 1) {
                    $authenticated = $country_password == $password_admin["admin"]["value"];
                } elseif ($country_id == 2) {
                    $authenticated = $country_password == $password_admin["region"]["value"];
                } else {
                    $authenticated = $country_password == $password_player["value"][$country_id];
                }

                if (!$authenticated) {
                    throw new Exception("That password is incorrect.");
                }
            } else {
                // an external username/password authentication provider should be used
                if ($this->callProvidersAuthentication($provider, $user_name, $country_password)) {
                    // now do authorization
                    if ($country_id == 1) {
                        $userlist = $password_admin["admin"]["value"];
                    } elseif ($country_id == 2) {
                        $userlist = $password_admin["region"]["value"];
                    } else {
                        $userlist = $password_player["value"][$country_id];
                    }
                    $userarray = explode("|", $userlist);
                    if (!in_array($user_name, $userarray)) {
                        throw new Exception("You are not allowed to log on for that country.");
                    }
                } else {
                    throw new Exception(
                        "Could not authenticate you. Your username and/or password could be incorrect."
                    );
                }
            }
            // all is well!
            $qb = $connection->createQueryBuilder()
                ->insert('user')
                ->values([
                    'user_name' => '?',
                    'user_lastupdate' => 0,
                    'user_country_id' => '?'
                ])
                ->setParameter(0, $user_name)
                ->setParameter(1, $country_id);
            $connection->executeQuery($qb->getSQL(), $qb->getParameters());
            $response['session_id'] = $connection->lastInsertId();
        }

        $sessionId = $response['session_id'];
        $this->logUserLogOnOrOff(
            true,
            $sessionId,
            $this->getGameSessionId(),
            $country_id
        );
        return $response;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function RequestSessionLegacy(
        int $countryId,
        string $countryPassword = "",
        string $userName = ""
    ): array {
        $response = array();
        $connection = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId());
        $qb = $connection->createQueryBuilder()
            ->select('game_session_password_admin', 'game_session_password_player')
            ->from('game_session');
        $passwords = $connection->executeQuery($qb->getSQL())->fetchAllAssociative();
        $hasCorrectPassword = true;
        if (count($passwords) > 0) {
            $password = ($countryId < 3) ?
                $passwords[0]["game_session_password_admin"] : $passwords[0]["game_session_password_player"];
            $hasCorrectPassword = $password == $countryPassword;
        }

        if ($hasCorrectPassword) {
            try {
                $qb = $connection->createQueryBuilder()
                    ->insert('user')
                    ->values([
                        'user_name' => '?',
                        'user_lastupdate' => 0,
                        'user_country_id' => '?'
                    ])
                    ->setParameter(0, $userName)
                    ->setParameter(1, $countryId);
                $connection->executeQuery($qb->getSQL(), $qb->getParameters());
                $response['session_id'] = $connection->lastInsertId();
                $security = new Security();
                $response["api_access_token"] = $security->generateToken()["token"];
                $response["api_access_recovery_token"] = $security->getRecoveryToken();
            } catch (Exception $e) {
                throw new Exception(
                    "Could not log you in. Please check with your session administrator." .
                    " This session might need upgrading." . $e->getMessage()
                );
            }
        } else {
            throw new Exception("Incorrect password.");
        }

        $sessionId = $response['session_id'];
        $this->logUserLogOnOrOff(
            true,
            $sessionId,
            $this->getGameSessionId(),
            $countryId
        );
        return $response;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CheckVersion(string $build_timestamp): void
    {
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $config = $game->GetGameConfigValues();

        if (array_key_exists("application_versions", $config)) {
            $clientBuildDate = DateTime::createFromFormat(DateTimeInterface::ATOM, $build_timestamp);
            $versionConfig = $config["application_versions"];
            $minDate = new DateTime("@0");
            $minBuildDate = $minDate;
            $maxBuildDate = new DateTime(); // now
            if (array_key_exists("client_build_date_min", $versionConfig)) {
                $minBuildDate = DateTime::createFromFormat(
                    DateTimeInterface::ATOM,
                    $versionConfig["client_build_date_min"]
                );
            }
            if (array_key_exists("client_build_date_max", $versionConfig)) {
                $maxBuildDate = DateTime::createFromFormat(
                    DateTimeInterface::ATOM,
                    $versionConfig["client_build_date_max"]
                );
            }
            if ($minBuildDate > $clientBuildDate || ($maxBuildDate > $minDate && $maxBuildDate < $clientBuildDate)) {
                if ($maxBuildDate > $minDate) {
                    $clientVersionsMessage = "Accepted client versions are between " .
                        $minBuildDate->format(DateTimeInterface::ATOM) . " and " .
                        $maxBuildDate->format(DateTimeInterface::ATOM) . ".";
                } else {
                    $clientVersionsMessage = "Accepted client versions are from " .
                        $minBuildDate->format(DateTimeInterface::ATOM) . " onwards.";
                }

                throw new Exception("Incompatible client version.\n" . $clientVersionsMessage .
                    "\nYour client version is " .
                    $clientBuildDate->format(DateTimeInterface::ATOM) . ".");
            }
        }
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CloseSession(int $session_id): void
    {
        //clean up all plans that are still locked by a user
        Database::GetInstance()->query(
            "UPDATE plan SET plan_lock_user_id=NULL WHERE plan_lock_user_id=?",
            array($session_id)
        );

        $sessionQueryResult = $this->getDatabase()->query(
            "SELECT user_name, user_country_id FROM user WHERE user_id = ?",
            array($session_id)
        );
        $this->getDatabase()->query("UPDATE user SET user_loggedoff = 1 WHERE user_id = ?", array($session_id));

        $userSession = empty($sessionQueryResult) ? [] : $sessionQueryResult[0];
        $countryId =  $userSession['user_country_id'] ?? -1;
        $this->logUserLogOnOrOff(
            false,
            $session_id,
            $this->getGameSessionId(),
            $countryId
        );
    }

    public static function checkProviderExists($provider): bool
    {
        return is_subclass_of($provider, Auths::class);
    }

    /**
     * @throws Exception
     */
    public static function getProviders(): array
    {
        $return = array();
        self::AutoloadAllClasses();
        foreach (get_declared_classes() as $class) {
            if (self::checkProviderExists($class)) {
                $return[] = [
                    "id" => $class,
                    "name" => (new $class)->getName()
                ];
            }
        }
        return $return;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function AutoloadAllClasses(): void
    {
        $classmap = require(APIHelper::getInstance()->GetBaseFolder() . 'vendor/composer/autoload_classmap.php');
        foreach ($classmap as $class => $file) {
            $refClass = new \ReflectionClass(__CLASS__);
            if (!Util::hasPrefix($class, str_replace($refClass->getShortName(), '', __CLASS__))) {
                continue;
            }
            $refClass = new \ReflectionClass($class);
            if (!$refClass->isSubclassOf(Auths::class)) {
                continue;
            }
            require_once($file);
        }
    }

    /**
     * @throws Exception
     */
    public static function checkExists(string $provider, string $users): array
    {
        if (self::checkProviderExists($provider)) {
            $call_provider = new $provider;
            return $call_provider->checkUser($users);
        }
        throw new Exception("Could not work with authentication provider '" . $provider . "'.");
    }

    public static function getProviderName(string $provider): string
    {
        if (self::checkProviderExists($provider)) {
            $call_provider = new $provider;
            return $call_provider->getName();
        }
        throw new Exception("Could not work with authentication provider '" . $provider . "'.");
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function List(int $country_id = 0)
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $qb->select('user_id', 'user_name', 'user_country_id')
            ->from('user')
            ->where($qb->expr()->eq('user_loggedoff', 0))
            ->andWhere($qb->expr()->lt('UNIX_TIMESTAMP() - user_lastupdate', 3600));
        if ($country_id > 0) {
            $qb->andWhere($qb->expr()->eq('user_country_id', $country_id));
        }
        return await(
            $this->getAsyncDatabase()->query($qb)
                ->then(function (Result $result) {
                    return $result->fetchAllRows();
                })
        );
    }

    /**
     * @throws Exception
     */
    private function callProvidersAuthentication(string $provider, string $username, string $password)
    {
        if ($this->checkProviderExists($provider)) {
            $call_provider = new $provider;
            return $call_provider->authenticate($username, $password);
        }
        throw new Exception("Could not work with authentication provider '" . $provider . "'.");
    }

    // all of the below is boilerplate to work with Symfony Security through LexikJWTAuthenticationBundle
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    /**
     * @param int|null $user_id
     */
    public function setUserId(?int $user_id): self
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->user_name;
    }

    public function setUsername(string $user_name): self
    {
        $this->user_name = $user_name;

        return $this;
    }

    public static function createFromPayload($username, array $payload): User
    {
        $user = new static;
        $user->setUserId($payload['uid']);
        $user->setUsername($username);
        return $user;
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

    /**
     * @throws Exception
     */
    private function logUserLogOnOrOff(
        bool $logOn,
        int $sessionId,
        int $gameSessionId,
        int $countryId
    ) : void {
        $legacyHelper = SymfonyToLegacyHelper::getInstance();
        $analyticsLogger = $legacyHelper->getAnalyticsLogger();

        $serverManager = ServerManager::getInstance();
        $serverManagerId = Uuid::fromString($serverManager->getServerUuid());

        $analyticsMessage = new UserLogOnOffSessionMessage(
            $logOn,
            new DateTimeImmutable(),
            $serverManagerId,
            $sessionId,
            $gameSessionId,
            $countryId
        );

        try {
            $legacyHelper->getMessageBus()->dispatch($analyticsMessage);
        } catch (Exception $e) {
            $analyticsLogger->error(
                "Exception occurred while dispatching user log ".
                ($logOn ? "on" : "off").
                " message : ". $e->getMessage()
            );
        }
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->getUserId();
    }
}
