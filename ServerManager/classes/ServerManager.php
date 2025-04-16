<?php

namespace ServerManager;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\Setting;
use App\VersionsProvider;
use Exception;

class ServerManager
{
    private static ?ServerManager $instance = null;

    // below properties will contain the value of settings record with names:
    //   server_id, server_name, server_description, server_uuid
    public ?string $serverId = null;
    public ?string $serverName = null;
    public ?string $serverDescription = null;
    public ?string $serverUuid = null;

    public ?string $serverAddress = null;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly VersionsProvider $versionProvider
    ) {
        self::$instance = $this;
    }

    /**
     * @throws Exception
     */
    private function completePropertiesFromDB(): void
    {
        $manager = $this->connectionManager->getServerManagerEntityManager();
        $settings = $manager->getRepository(Setting::class)->findAll();
        foreach ($settings as $setting) {
            $name = $setting->getName();
            // snake to lower camel case
            $propertyName = lcfirst(str_replace('_', '', ucwords($name, '_')));
            if (property_exists($this, $propertyName)) {
                $this->$propertyName = $setting->getValue();
            }
        }

        $this->serverAddress = $manager->getRepository(GameServer::class)->findOneBy(['id' => 1])->getAddress();
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new Exception(
                'Instance is unavailable. It should be set by first constructor call, using Symfony services.'
            );
        }
        return self::$instance;
    }

    public function getCurrentVersion(): string
    {
        return $this->versionProvider->getVersion();
    }

    /**
     * @throws Exception
     */
    public function getServerUuid(): ?string
    {
        if (is_null($this->serverUuid)) {
            $this->completePropertiesFromDB();
        }
        return $this->serverUuid;
    }

    public function getTranslatedServerURL(): string
    {
        $port = $_ENV['URL_WEB_SERVER_PORT'] ?? $_ENV['WEB_SERVER_PORT'] ?? 80;
        if (($_ENV['URL_WEB_SERVER_HOST'] ?? '') !== '') {
            return $_ENV['URL_WEB_SERVER_HOST'].':'.$port;
        }

        if (empty($this->serverAddress)) {
            try {
                $this->completePropertiesFromDB();
            } catch (Exception $e) {
                // silent fail.
            }
        }
        // e.g. localhost
        if (!empty($_SERVER['SERVER_NAME'])) {
            if ($_SERVER['SERVER_NAME'] != $this->serverAddress) {
                return $_SERVER['SERVER_NAME'].':'.$port;
            }
        }

        return $this->serverAddress.':'.$port;
    }
}
