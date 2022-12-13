<?php

namespace ServerManager;

use App\Domain\Helper\Config;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\WsServer;

class ServerManager extends Base
{
    private static ?ServerManager $instance = null;
    private ?ServerManager $old = null;
    private $db;
    private array $serverVersions = [];
    private array $serverAcceptedClients = [];
    private string|false $serverCurrentVersion = false;
    private $serverRoot;
    private string $serverManagerRoot = '';
    private array $serverUpgrades = [];
    public $server_id;
    public ?string $server_name = null;
    public $server_address;
    public $server_description;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->serverVersions = [
          '4.0-beta7',
          '4.0-beta8',
          '4.0-beta9',
          '4.0-beta10',
        ];
        $this->serverAcceptedClients = [
          '4.0-beta8' => '2021-04-20 13:54:41Z',
          '4.0-beta9' => '2021-11-08 08:13:08Z',
          '4.0-beta10' => '2022-05-24 00:00:00Z',
        ];
        $this->serverCurrentVersion = end($this->serverVersions);
        $this->serverUpgrades = [ // make sure these functions exist in server API update class and is actually
            // callable - just letters and numbers of course
          'From40beta7To40beta8',
          'From40beta7To40beta9',
          'From40beta7To40beta10',
          'From40beta8To40beta10',
          'From40beta9To40beta10',
        ];
        $this->setRootVars();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CompletePropertiesFromDB()
    {
        $this->db = DB::getInstance();
        $this->server_id = $this->db->cell('settings.value', ['name', '=', 'server_id']);
        $this->server_name = $this->db->cell('settings.value', ['name', '=', 'server_name']);
        $this->server_address = $this->db->cell('game_servers.address', ['id', '=', 1]);
        $this->server_description = $this->db->cell('settings.value', ['name', '=', 'server_description']);

        $this->old = clone $this;
    }

    /**
     * @throws \Exception
     */
    private function setRootVars()
    {
        $server_root = SymfonyToLegacyHelper::getInstance()->getProjectDir();
        $server_manager_root = '';
        $self_path = explode('/', $_SERVER['PHP_SELF']);
        $self_path_length = count($self_path);
        for ($i = 1; $i < $self_path_length; ++$i) {
            array_splice($self_path, $self_path_length - $i, $i);
            $server_manager_root = implode('/', $self_path).'/';
            if (file_exists($server_root.$server_manager_root.'init.php')) {
                break;
            }
        }
        $this->serverRoot = $server_root.'/';
        $this->serverManagerRoot = ltrim($server_manager_root, '/');
    }

    public static function getInstance(): ?ServerManager
    {
        if (!isset(self::$instance)) {
            self::$instance = new ServerManager();
        }

        return self::$instance;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CheckForUpgrade($versiondetermined): array|bool|string|null
    {
        if (!empty($versiondetermined)) {
            // postulate the upgrade function name
            $upgradefunction = preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                'From'.$versiondetermined.'To'.$this->serverCurrentVersion
            );
            // see if it's in the _server_upgrades list
            if (in_array($upgradefunction, $this->serverUpgrades)) {
                return $upgradefunction;
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function IsClientAllowed($timestamp): bool
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

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetMSPAuthAPI(): string
    {
        return $this->GetMSPAuthURL().'/usersc/plugins/apibuilder/authmsp/';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetMSPAuthURL(): string
    {
        return \App\Domain\API\v1\Config::GetInstance()->getMSPAuthBaseURL();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAllVersions(): array
    {
        return $this->serverVersions;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCurrentVersion(): bool|string
    {
        return $this->serverCurrentVersion;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerID()
    {
        if (is_null($this->server_id)) {
            $this->CompletePropertiesFromDB();
        }

        return $this->server_id;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerName()
    {
        if (empty($this->server_name)) {
            $this->CompletePropertiesFromDB();
        }

        return $this->server_name;
    }

    public function freshInstall(): bool
    {
        if (empty($this->server_id)) {
            $this->CompletePropertiesFromDB();
        }

        return empty($this->server_id);
    }

    public function install($user = null): bool
    {
        if ($this->freshInstall()) {
            $this->SetServerID();
            $this->SetServerName($user);
            $this->SetServerDescription();

            return true;
        }

        return false;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetServerAddress($user = null)
    {
        $this->db->query('UPDATE game_servers SET address = ? WHERE id = 1;', [$this->server_address]);

        return $this->server_address;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetServerID(): string
    {
        if (empty($this->server_id)) {
            $this->server_id = uniqid('', true);
            $this->db->query('INSERT INTO settings (name, value) VALUES (?, ?);', ['server_id', $this->server_id]);
        }

        return $this->server_id;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetServerName($user = null): bool|string
    {
        if (empty($this->server_name)) {
            if (empty($user)) {
                return false;
            }
            $this->server_name = $user->data()->username.'_'.date('Ymd');
        }

        if ($this->old !== null &&
            $this->old->server_name == $this->server_name) {
            return $this->server_name; // no need to do anything if nothing changes
        }

        $try_update = $this->db->query(
            'UPDATE settings SET value = ? WHERE name = ?;',
            [$this->server_name, 'server_name']
        );
        if ($try_update && 0 == $this->db->count()) {
            $this->db->query(
                'INSERT INTO settings (name, value) VALUES (?, ?);',
                ['server_name', $this->server_name]
            );
        }

        return $this->server_name;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetServerDescription(): string
    {
        if (empty($this->server_description)) {
            $this->server_description = 'This is a new MSP Challenge server installation. The administrator has not '.
                'changed this default description yet. This can be done through the ServerManager.';
        }

        if ($this->old !== null &&
            $this->old->server_description == $this->server_description) {
            return $this->server_description; // no need to do anything if nothing changes
        }

        $try_update = $this->db->query('UPDATE settings SET value = ? WHERE name = ?;', [
            $this->server_description, 'server_description', ]);
        if ($try_update && 0 == $this->db->count()) {
            $this->db->query('INSERT INTO settings (name, value) VALUES (?, ?);', [
                'server_description', $this->server_description, ]);
        }

        return $this->server_description;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerURLBySessionId($sessionId = ''): string
    {
        // e.g. http://localhost/1
        // use this one if you just want the full URL of a Server's session
        $url = Config::get('msp_server_protocol').$this->GetTranslatedServerURL().Config::get('code_branch');
        if (!empty($sessionId)) {
            $url .= '/'.$sessionId;
        }

        return $url;
    }

    public function getWsServerURLBySessionId(int $sessionId = 0): string
    {
        $urlParts = parse_url($this->GetTranslatedServerURL());

        return WsServer::getWsServerURLBySessionId($sessionId, $urlParts['host'] ?: 'localhost');
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetFullSelfAddress(): string
    {
        // e.g. http://localhost/ServerManager/
        // use this one if you just want the full URL of the ServerManager
        return $this->GetBareHost().Config::get('code_branch').$this->serverManagerRoot;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetBareHost(): string
    {
        // e.g. http://localhost
        return Config::get('msp_servermanager_protocol').$this->GetTranslatedServerURL();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetTranslatedServerURL(): string
    {
        if (empty($this->server_address)) {
            $this->CompletePropertiesFromDB();
        }
        // e.g. localhost
        if (!empty($_SERVER['SERVER_NAME'])) {
            if ($_SERVER['SERVER_NAME'] != $this->server_address) {
                return $_SERVER['SERVER_NAME'].':'.($_ENV['WEB_SERVER_PORT'] ?? 80);
            }
        }

        return $this->server_address.':'.($_ENV['WEB_SERVER_PORT'] ?? 80);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerRoot()
    {
        // e.g. C:/Program Files/MSP Challenge/Server/
        // use this if you just want the folder location of the Server
        return $this->serverRoot;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerManagerRoot(): string
    {
        // e.g. C:/Program Files/MSP Challenge/Server/ServerManager/
        // use this if you just want the folder location of the ServerManager
        return $this->serverRoot.$this->serverManagerRoot;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerManagerFolder(): string
    {
        // e.g. /ServerManager/
        return '/'.$this->serverManagerRoot;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetConfigBaseDirectory(): string
    {
        $dir = $this->GetServerManagerRoot().'configfiles/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionArchiveBaseDirectory(): string
    {
        $dir = $this->GetServerManagerRoot().'session_archive/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionArchivePrefix(): string
    {
        return 'session_archive_';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionSavesBaseDirectory(): string
    {
        $dir = $this->GetServerManagerRoot().'saves/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionSavesPrefix(): string
    {
        return 'save_';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionLogBaseDirectory(): string
    {
        $dir = $this->GetServerManagerRoot().'log/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSessionLogPrefix(): string
    {
        return 'log_session_';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerConfigBaseDirectory(): string
    {
        $dir = $this->GetServerRoot().'running_session_config/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerRasterBaseDirectory(): string
    {
        $dir = $this->GetServerRoot().'raster/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerSessionArchiveBaseDirectory(): string
    {
        $dir = $this->GetServerRoot().'session_archive/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        return $dir;
    }

    public function edit()
    {
        $this->SetServerName();
        $this->SetServerAddress();
        $this->SetServerDescription();

        Base::callAuthoriser( // doing this here because JWT won't be available elsewhere
            'updateservernamejwt.php',
            [
            'jwt' => $this->getJWT(),
            'audience' => $this->GetBareHost(),
            'server_id' => $this->GetServerID(),
            'server_name' => $this->GetServerName(),
            ]
        );
    }

    public function get()
    {
        $this->CompletePropertiesFromDB();
    }
}
