<?php

namespace ServerManager;

use App\Domain\Helper\Config;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\WsServer;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class ServerManager extends Base
{
    private const SERVER_MANAGER_ROOT_SUB_DIR = 'ServerManager/';

    private static ?ServerManager $instance = null;
    private ?ServerManager $old = null;
    private array $serverVersions = [];
    private array $serverAcceptedClients = [];
    private string|false $serverCurrentVersion = false;
    private string $serverManagerRoot;
    private array $serverUpgrades = [];
    public ?string $serverUuid = null;
    public ?string $serverId = null;
    public ?string $serverName = null;
    public ?string $serverAddress = null;
    public ?string $serverDescription = null;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        self::$instance = $this;
        $this->serverVersions = [
          '4.0-beta7',
          '4.0-beta8',
          '4.0-beta9',
          '4.0-beta10',
          '4.0-rc1',
          '4.0-rc2',
          '4.0-rc3'
        ];
        $this->serverAcceptedClients = [
          '4.0-beta8' => '2021-04-20 13:54:41Z',
          '4.0-beta9' => '2021-11-08 08:13:08Z',
          '4.0-beta10' => '2022-05-24 00:00:00Z',
          '4.0-rc1' => '2023-02-02 00:00:00Z',
          '4.0-rc2' => '2023-02-02 00:00:00Z',
          '4.0-rc3' => '2023-07-10 00:00:00Z'
        ];
        $versionProvider = SymfonyToLegacyHelper::getInstance()->getProvider();
        $this->serverVersions[] = $versionProvider->getVersion();
        $this->serverAcceptedClients[$versionProvider->getVersion()] = date(
            "Y-m-d H:i:s",
            filemtime(SymfonyToLegacyHelper::getInstance()->getProjectDir().DIRECTORY_SEPARATOR.'version.txt')
        );
        $this->serverCurrentVersion = end($this->serverVersions);
        $this->serverUpgrades = [ // make sure these functions exist in server API update class and is actually
            // callable - just letters and numbers of course
          'From40beta7To40beta8',
          'From40beta7To40beta9',
          'From40beta7To40beta10',
          'From40beta8To40beta10',
          'From40beta9To40beta10'
        ];
        $this->serverManagerRoot = $this->projectDir.'/'.self::SERVER_MANAGER_ROOT_SUB_DIR;
    }

    private function completePropertiesFromDB()
    {
        $manager = $this->em;
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

        $this->old = clone $this;
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new Exception(
                'Instance is unavailable. It should be set by first constructor call, using Symfony services.'
            );
        }
        return self::$instance;
    }

    public function checkForUpgrade($versiondetermined): array|bool|string|null
    {
        if (!empty($versiondetermined)) {
            // migration support was added with beta7
            // starting with beta10, migrations are handled by Doctrine's db migrations system
            // thus, if this session db is pre-beta-10, then simply run From40beta[X]To40beta10
            // postulate the upgrade function name
            $upgradefunction = preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                'From'.$versiondetermined.'To4.0-beta10'
            );
            // see if it's in the _server_upgrades list
            if (in_array($upgradefunction, $this->serverUpgrades)) {
                return $upgradefunction;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function isClientAllowed($timestamp): bool
    {
        if (!isset($this->serverAcceptedClients[$this->serverCurrentVersion])) {
            return true;
        }

        $minimum_client_version = (new \DateTime(
            $this->serverAcceptedClients[$this->serverCurrentVersion]
        ))->format('U');
        $requested_version = (new \DateTime($timestamp))->format('U');
        if ($requested_version < $minimum_client_version) {
            return false;
        }

        return true;
    }

    public function getMSPAuthAPI(): string
    {
        return $this->getMSPAuthBaseURL().($_ENV['AUTH_SERVER_API_BASE_PATH'] ?? '/api/');
    }

    public function getMSPAuthBaseURL(): string
    {
        return \App\Domain\API\v1\Config::GetInstance()->getMSPAuthBaseURL();
    }

    public function getCurrentVersion(): bool|string
    {
        return $this->serverCurrentVersion;
    }

    public function getServerId(): ?string
    {
        if (is_null($this->serverId)) {
            $this->completePropertiesFromDB();
        }

        return $this->serverId;
    }

    public function getServerPassword()
    {
        return $this->em
            ->getRepository(Setting::class)->findOneBy(['name' => 'server_password'])
            ->getValue();
    }

    public function getServerUuid(): ?string
    {
        if (is_null($this->serverUuid)) {
            $this->completePropertiesFromDB();
        }

        return $this->serverUuid;
    }

    public function getServerName(): ?string
    {
        if (empty($this->serverName)) {
            $this->completePropertiesFromDB();
        }

        return $this->serverName;
    }

    public function freshInstall(): bool
    {
        $manager = $this->em;
        $settings = $manager->getRepository(Setting::class)->findBy(['name' => 'server_id']);
        return empty($settings);
    }

    public function install($user = null): bool
    {
        if ($this->freshInstall()) {
            $this->setServerId();
            $this->setServerName($user);
            $this->setServerDescription();

            $manager = $this->em;
            $this->serverUuid = Uuid::v4();
            $settingServerUUID = new Setting('server_uuid', $this->serverUuid);
            $manager->persist($settingServerUUID);
            $settingServerPassword = new Setting('server_password', (string) time());
            $manager->persist($settingServerPassword);
            $manager->flush();

            return true;
        }

        return false;
    }

    public function setServerAddress(): ?string
    {
        $manager = $this->em;
        $serverAddress = $manager->getRepository(GameServer::class)->findOneBy(['id' => 1]);
        $serverAddress->setAddress($this->serverAddress);
        $manager->persist($serverAddress);
        $manager->flush();

        return $this->serverAddress;
    }

    private function setServerId(): string
    {
        if (empty($this->serverId)) {
            $this->serverId = uniqid('', true);
            $manager = $this->em;
            $settingServerID = new Setting('server_id', $this->serverId);
            $manager->persist($settingServerID);
            $manager->flush();
        }
        return $this->serverId;
    }

    public function setServerName($user = null): bool|string
    {
        if (empty($this->serverName)) {
            if (empty($user)) {
                return false;
            }
            $this->serverName = $user->data()->username.'_'.date('Ymd');
        }

        if ($this->old !== null &&
            $this->old->serverName == $this->serverName) {
            return $this->serverName; // no need to do anything if nothing changes
        }

        $manager = $this->em;
        $settingServerAddress = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_name']);
        if (is_null($settingServerAddress)) {
            $settingServerAddress = new Setting();
            $settingServerAddress->setName('server_name');
        }
        $settingServerAddress->setValue($this->serverName);
        $manager->persist($settingServerAddress);
        $manager->flush();
        return $this->serverName;
    }

    public function setServerDescription(): string
    {
        if (empty($this->serverDescription)) {
            $this->serverDescription = 'This is a new MSP Challenge server installation. The administrator has not '.
                'changed this default description yet. This can be done through the ServerManager.';
        }

        $manager = $this->em;
        $settingServerDesc = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_description']);
        if (is_null($settingServerDesc)) {
            $settingServerDesc = new Setting();
            $settingServerDesc->setName('server_description');
        }
        $settingServerDesc->setValue($this->serverDescription);
        $manager->persist($settingServerDesc);
        $manager->flush();
        return $this->serverDescription;
    }

    public function getServerURLBySessionId($sessionId = '', bool $forDocker = false): string
    {
        // e.g. http://localhost/1
        // use this one if you just want the full URL of a Server's session
        $url = $forDocker ?
            // this is always called from inside the docker environment,so just use http://php:80/...
            'http://php:80'.Config::get('code_branch') :
            Config::get('msp_server_protocol').$this->getTranslatedServerURL().Config::get('code_branch');
        if (!empty($sessionId)) {
            $url = rtrim($url, '/').'/'.$sessionId;
        }
        return $url;
    }

    public function getWsServerURLBySessionId(int $sessionId = 0): string
    {
        $urlParts = parse_url($this->GetTranslatedServerURL());

        return WsServer::getWsServerURLBySessionId($sessionId, $urlParts['host'] ?: 'localhost');
    }

    public function getAbsoluteUrlBase(): string
    {
        // e.g. http://localhost/ServerManager/
        // use this one if you just want the full URL of the ServerManager
        return $this->urlGenerator->generate('server_manager_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getAbsolutePathBase(): string
    {
        // e.g. /ServerManager/
        return $this->urlGenerator->generate('server_manager_index');
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

    public function getServerManagerRoot(): string
    {
        // e.g. C:/Program Files/MSP Challenge/Server/ServerManager/
        // use this if you just want the folder location of the ServerManager
        return $this->serverManagerRoot;
    }

    public function getConfigBaseDirectory(): string
    {
        $dir = $this->getServerManagerRoot().'configfiles/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function getSessionArchiveBaseDirectory(): string
    {
        $dir = $this->getServerManagerRoot().'session_archive/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function getSessionArchivePrefix(): string
    {
        return 'session_archive_';
    }

    public function getSessionSavesBaseDirectory(): string
    {
        $dir = $this->getServerManagerRoot().'saves/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function getSessionSavesPrefix(): string
    {
        return 'save_';
    }

    public function getSessionLogBaseDirectory(): string
    {
        $dir = $this->getServerManagerRoot().'log/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function getSessionLogPrefix(): string
    {
        return 'log_session_';
    }

    /**
     * @throws HydraErrorException
     */
    public function edit()
    {
        $this->setServerName();
        $this->setServerAddress();
        $this->setServerDescription();

        $this->putCallAuthoriser( // doing this here because JWT won't be available elsewhere
            sprintf('servers/%s', $this->GetServerUUID()),
            [
                'serverName' => $this->getServerName(),
            ]
        );
    }

    public function get()
    {
        $this->completePropertiesFromDB();
    }
}
