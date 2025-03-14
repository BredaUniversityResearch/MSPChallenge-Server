<?php

namespace App\Domain\API\v1;

use App\Domain\API\APIHelper;

class Config
{
    private const DEFAULT_GAME_AUTOSAVE_INTERVAL = 120;

    private static ?Config $instance = null;
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetInstance(): self
    {
        if (self::$instance == null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    private ?array $configRoot = null;

    private function __construct()
    {
        $this->LoadConfigFile();
    }

    /**
     * @throws \Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function LoadConfigFile(): void
    {
        require_once(APIHelper::getInstance()->GetBaseFolder().'api_config.php');
        $this->configRoot = $GLOBALS['api_config'];
    }

    public function getMSPAuthBaseURL(): string
    {
        return str_replace('://', '', $_ENV['AUTH_SERVER_SCHEME'] ?? 'https').'://'.
            ($_ENV['AUTH_SERVER_HOST'] ?? 'auth2.mspchallenge.info').':'.
            ($_ENV['AUTH_SERVER_PORT'] ?? 443);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAuth(): string
    {
        return $this->getMSPAuthBaseURL() . '/api/';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAuthJWTRetrieval(): string
    {
        return $this->GetAuth().'login_check';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAuthJWTUserCheck(): string
    {
        return $this->GetAuth().'users';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAuthJWTUserEmailCheck($username): string
    {
        return $this->GetAuth().'users/'.$username;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetGameAutosaveInterval(): int
    {
        return $this->configRoot['game_autosave_interval'] ?? self::DEFAULT_GAME_AUTOSAVE_INTERVAL;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetLongRequestTimeout(): int
    {
        return $this->configRoot['long_request_timeout'] ?? ini_get('max_execution_time');
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ShouldWaitForSimulationsInDev(): bool
    {
        return $this->configRoot['wait_for_simulations_in_dev'] ?? false;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function WikiConfig(): array
    {
        return $this->configRoot['wiki'] ?? [];
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DatabaseConfig(): array
    {
        return $this->configRoot['database'] ?? [];
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetUnitTestLoggerConfig(): array
    {
        return $this->configRoot['unit_test_logger'] ?? [];
    }
}
