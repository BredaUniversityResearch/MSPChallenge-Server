<?php

namespace ServerManager;

use App\Domain\API\v1\Game;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameConfigVersion;
use App\Message\Analytics\SessionCreatedMessage;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Uid\Uuid;

class GameSession extends Base
{
    private ?DB $db = null;
    private ?GameSession $old = null;

    public $name;
    public $game_config_version_id;
    public $game_server_id;
    public $game_geoserver_id;
    public $watchdog_server_id;
    public $game_creation_time;
    public $game_start_year;
    public $game_end_month;
    public $game_current_month;
    public $game_running_til_time;
    public $password_admin;
    public $password_player;
    public $session_state;
    public $game_state;
    public $game_visibility;
    public $players_active;
    public $players_past_hour;
    public $demo_session;
    public $api_access_token;
    public $save_id;
    public $server_version;
    public $log;
    public $id;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    private function validateVars()
    {
        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $var) {
            $varname = $var->getName();
            switch ($varname) {
                case 'password_player':
                case 'password_admin':
                    $this->$varname = $this->CheckPasswordFormat($varname, $this->$varname);
                    break;
                case 'name':
                    if (self::HasSpecialChars($this->name)) {
                        throw new ServerManagerAPIException('Session name cannot contain special characters.');
                    }
                    break;
                case 'session_state':
                    if (!in_array($this->session_state, ['request', 'initializing', 'healthy', 'failed', 'archived'])) {
                        throw new ServerManagerAPIException('That session state is not allowed.');
                    }
                    break;
                case 'game_state':
                    $this->game_state = strtolower($this->game_state);
                    if (!in_array($this->game_state, ['setup', 'simulation', 'play', 'pause', 'end', 'fastforward'])) {
                        throw new ServerManagerAPIException('That game state is not allowed.');
                    }
                    break;
                case 'log':
                case 'game_geoserver_id':
                case 'save_id':
                case 'game_config_version_id':
                    break;
                default:
                    if (0 == strlen($this->$varname)) {
                        throw new ServerManagerAPIException('Missing value for '.$varname);
                    }
            }
        }
    }

    public function get()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot obtain GameSession without a valid id.');
        }
        $this->db->query('SELECT * FROM game_list WHERE id = ?', [$this->id]);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        if (0 == $this->db->count()) {
            throw new ServerManagerAPIException('Session not found.');
        }
        foreach ($this->db->first(true) as $varname => $varvalue) {
            if (property_exists($this, $varname)) {
                $this->$varname = $varvalue;
            }
        }

        // backwards compatibility (beta7 and earlier), when the password fields were unencoded strings
        if (Base::isNewPasswordFormat($this->password_admin)) {
            $this->password_admin = base64_decode($this->password_admin);
        }
        $this->password_admin = $this->CheckPasswordFormat('password_admin', $this->password_admin);
        if (Base::isNewPasswordFormat($this->password_player)) {
            $this->password_player = base64_decode($this->password_player);
        }
        $this->password_player = $this->CheckPasswordFormat('password_player', $this->password_player);

        $log_dir = ServerManager::getInstance()->getSessionLogBaseDirectory();
        $log_path = $log_dir.ServerManager::getInstance()->getSessionLogPrefix().$this->id.'.log';
        if (file_exists($log_path)) {
            $log_contents = file_get_contents($log_path);
            if (false === $log_contents) {
                $this->log = 'Session log does not exist (yet).';
            } else {
                $this->log = explode(PHP_EOL, rtrim($log_contents));
            }
        }
        $this->old = clone $this;
    }

    public function add()
    {
        $this->validateVars();
        $sql = 'INSERT INTO `game_list` 
                (   name, 
                    game_config_version_id, 
                    game_server_id, 
                    game_geoserver_id, 
                    watchdog_server_id,
                    game_creation_time, 
                    game_start_year, 
                    game_end_month, 
                    game_current_month, 
                    game_running_til_time,
                    password_admin, 
                    password_player, 
                    session_state,
                    game_state, 
                    game_visibility, 
                    players_active, 
                    players_past_hour, 
                    demo_session,
                    api_access_token,
                    save_id,
                    server_version
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->save_id = $this->save_id ?: null;
        $args = getPublicObjectVars($this);
        $args['password_admin'] = base64_encode($args['password_admin']);
        $args['password_player'] = base64_encode($args['password_player']);
        unset($args['id']);
        unset($args['log']);
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $this->id = $this->db->lastId();

        $this->logSessionCreation();
    }

    public function sendLoadRequest($allow_recreate = 0)
    {
        $gamesave = new GameSave();
        $gamesave->id = $this->save_id;

        $watchdog = new Watchdog();
        $watchdog->id = $this->watchdog_server_id;
        $watchdog->get();

        $server_call = self::callServer(
            'GameSession/LoadGameSave',
            [
                'save_path' => $gamesave->getFullZipPath(),
                'watchdog_address' => $watchdog->address,
                'game_id' => $this->id,
                'response_address' => ServerManager::getInstance()->getAbsoluteUrlBase().'api/editGameSession.php',
                'allow_recreate' => $allow_recreate,
            ]
        );
        if (!$server_call['success']) {
            throw new ServerManagerAPIException($server_call['message']);
        }
    }

    public function sendCreateRequest($allow_recreate = 0)
    {
        $geoserver = new GeoServer();
        $geoserver->id = $this->game_geoserver_id;
        $geoserver->get();

        $gameconfig = new GameConfig();
        $gameconfig->id = $this->game_config_version_id;
        $gameconfig->get();

        $watchdog = new Watchdog();
        $watchdog->id = $this->watchdog_server_id;
        $watchdog->get();

        $dataArray = [
            'game_id' => $this->id,
            'config_file' => $gameconfig->getFile(),
            'geoserver_url' => $geoserver->address,
            'geoserver_username' => $geoserver->username,
            'geoserver_password' => $geoserver->password,
            'password_admin' => base64_encode($this->password_admin),
            'password_player' => base64_encode($this->password_player),
            'watchdog_address' => $watchdog->address,
            'allow_recreate' => $allow_recreate,
            'response_address' => ServerManager::getInstance()->getAbsoluteUrlBase().'api/editGameSession.php',
        ];
        $server_call = self::callServer(
            'GameSession/CreateGameSession',
            $dataArray,
            $this->id
        );
        if (empty($server_call['success'])) {
            if (0 == $allow_recreate) {
                $this->revert();
            }
            if (is_string($server_call)) {
                $server_call = [];
                $server_call['message'] = $server_call;
            }
            throw new ServerManagerAPIException($server_call['message'] ?? 'Unknown error');
        }

        $gameconfig->last_played_time = time();
        $gameconfig->edit();

        $this->postCallAuthoriser('logs', [
            'level' => '200',
            'message' => sprintf('%s|%s', $gameconfig->region, $this->id)
        ]);
    }

    public function recreate(): bool
    {
        if ($this->game_state != 'end') {
            $this->game_state = 'end';
            $this->changeGameState();
        }
        if ($this->save_id > 0) {
            return $this->reload();
        }

        // after a server upgrade the version might have changed since session was first created
        $this->server_version = ServerManager::getInstance()->getCurrentVersion();
        $this->sendCreateRequest(1);
        $this->setToLoading();

        return true;
    }

    private function reload(): bool
    {
        $gamesave = new GameSave();
        $gamesave->id = $this->save_id;
        $gamesave->get();

        $this->game_start_year = $gamesave->game_start_year;
        $this->game_end_month = $gamesave->game_end_month;
        $this->game_current_month = $gamesave->game_current_month;
        if (Base::isNewPasswordFormat($gamesave->password_admin)) {
            $gamesave->password_admin = base64_decode($gamesave->password_admin);
        }
        $this->password_admin = $gamesave->password_admin;
        if (Base::isNewPasswordFormat($gamesave->password_player)) {
            $gamesave->password_player = base64_decode($gamesave->password_player);
        }
        $this->password_player = $gamesave->password_player;
        $this->game_state = $gamesave->game_state;
        $this->game_visibility = $gamesave->game_visibility;
        $this->players_active = $gamesave->players_active;
        $this->players_past_hour = $gamesave->players_past_hour;
        if (empty($this->demo_session)) { // in case a previous reload became a demo session, it should remain that upon
            // next reload
            $this->demo_session = $gamesave->demo_session;
        }
        $this->api_access_token = $gamesave->api_access_token;
        $this->server_version = $gamesave->server_version;

        $this->sendLoadRequest(1);
        $this->setToLoading();

        return true;
    }

    private function setToLoading()
    {
        $now = time();
        $this->session_state = 'request';
        $this->game_creation_time = $now;
        $this->game_running_til_time = $now;
    }

    private function revert()
    {
        $this->db->query('DELETE FROM game_list WHERE id = ?;', [$this->id]);
    }

    public function demoCheck(): bool
    {
        if (null === $this->old) {
            throw new ServerManagerAPIException("Cannot continue as I don't have original GameSession object.");
        }

        if (1 == $this->demo_session) {
            if ('healthy' == $this->old->session_state &&
                ('pause' == $this->old->game_state || 'setup' == $this->old->game_state)) {
                // healthy demo sessions need to return to play if previously on pause or setup
                $this->game_state = 'play';
                $this->changeGameState();
            } elseif ('healthy' == $this->session_state && 'end' == $this->game_state) {
                // demo sessions need to be recreated as soon as they have ended
                $this->recreate();
            }
        }

        return true;
    }

    public function edit()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot update without knowing which id to use.');
        }
        $this->validateVars();
        $this->save_id = $this->save_id ?: null;
        $args = getPublicObjectVars($this);
        $args['password_admin'] = base64_encode($args['password_admin']);
        $args['password_player'] = base64_encode($args['password_player']);
        unset($args['log']);
        $sql = 'UPDATE game_list SET 
                    name = ?,
                    game_config_version_id = ?,
                    game_server_id = ?,
                    game_geoserver_id = ?,
                    watchdog_server_id = ?,
                    game_creation_time = ?,
                    game_start_year = ?,
                    game_end_month = ?,
                    game_current_month = ?,
                    game_running_til_time = ?,
                    password_admin = ?,
                    password_player = ?,
                    session_state = ?,
                    game_state = ?,
                    game_visibility = ?,
                    players_active = ?,
                    players_past_hour = ?,
                    demo_session = ?,
                    api_access_token = ?,
                    save_id = ?,
                    server_version = ?
                WHERE id = ?';
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CheckPasswordFormat($adminorplayer, $string): bool|string
    {
        if (isJsonObject($string)) {
            // backwards compatibility
            $string_decoded = json_decode($string, true);
            if (isset($string_decoded['admin'])) {
                if (isset($string_decoded['admin']['password'])) {
                    $string_decoded['admin']['value'] = $string_decoded['admin']['password'];
                    unset($string_decoded['admin']['password']);
                } elseif (isset($string_decoded['admin']['users'])) {
                    if (is_array($string_decoded['admin']['users'])) {
                        $string_decoded['admin']['users'] = implode(' ', $string_decoded['admin']['users']);
                    }
                    $string_decoded['admin']['value'] = $string_decoded['admin']['users'];
                    unset($string_decoded['admin']['users']);
                }
                if (isset($string_decoded['region']['password'])) {
                    $string_decoded['region']['value'] = $string_decoded['region']['password'];
                    unset($string_decoded['region']['password']);
                } elseif (isset($string_decoded['region']['users'])) {
                    if (is_array($string_decoded['region']['users'])) {
                        $string_decoded['region']['users'] = implode(' ', $string_decoded['region']['users']);
                    }
                    $string_decoded['region']['value'] = $string_decoded['region']['users'];
                    unset($string_decoded['region']['users']);
                }
            } elseif (isset($string_decoded['password'])) {
                $string_decoded['value'] = $string_decoded['password'];
                unset($string_decoded['password']);
            } elseif (isset($string_decoded['users'])) {
                if (is_array($string_decoded['users'])) {
                    $string_decoded['users'] = implode(' ', $string_decoded['users']);
                }
                $string_decoded['value'] = $string_decoded['users'];
                unset($string_decoded['users']);
            }

            return json_encode($string_decoded);
        }

        // only used when creating new session or loading a save from pre-beta8
        if ('password_admin' == $adminorplayer) {
            $newarray['admin']['provider'] = 'local';
            $newarray['admin']['value'] = (string) $string;
            $newarray['region']['provider'] = 'local';
            $newarray['region']['value'] = (string) $string;
        } else {
            $newarray['provider'] = 'local';
            $countries = $this->getCountries();
            if (false !== $countries) {
                foreach ($countries as $country_data) {
                    $newarray['value'][$country_data['country_id']] = (string) $string;
                }
            }
        }

        return json_encode($newarray);
    }

    public function getCountries(): array
    {
 // using config files rather than the session database for this, as this function can be called pre session existence
        if (!empty($this->save_id)) { // session eminates from a save as save_id is neither null nor 0
            $gamesave = new GameSave();
            $gamesave->id = $this->save_id;
            $gamesave->get();
            $configData = $gamesave->getContentsConfig();
        } else { // session eminates from a config file, so from scratch
            if (empty($this->game_config_version_id)) {
                throw new ServerManagerAPIException('Cannot obtain GameConfig without a valid id.');
            }
            $gameconfig = new GameConfig();
            $gameconfig->id = $this->game_config_version_id;
            $gameconfig->get();
            $configData = $gameconfig->getContents();
        }

        $countries = [];
        if (!isset($configData['datamodel']) || !isset($configData['datamodel']['meta'])) {
            return $countries;
        }

        foreach ($configData['datamodel']['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['datamodel']['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countries[] = [
                        'country_id' => $country['value'],
                        'country_name' => $country['displayName'],
                        'country_colour' => $country['polygonColor'],
                    ];
                }
            }
        }

        return $countries;
    }

    public function setUserAccess(): bool
    {
        $server_call = self::callServer(
            'GameSession/SetUserAccess',
            [
                'password_admin' => base64_encode($this->password_admin),
                'password_player' => base64_encode($this->password_player),
            ],
            $this->id,
            $this->api_access_token
        );
        if (!$server_call['success']) {
            throw new ServerManagerAPIException($server_call['message']);
        }

        return true;
    }

    public function getList($where_array = []): array
    {
        if (isset($_POST['client_timestamp']) && !ServerManager::getInstance()->isClientAllowed(
            $_POST['client_timestamp']
        )) {
            return $this->mustUpdateBogusList();
        }

        if (!$this->db->action(
            "SELECT games.id, games.name, games.game_config_version_id, games.game_server_id, games.watchdog_server_id,
            games.game_creation_time, games.game_start_year, games.game_end_month, games.game_current_month,
            games.game_running_til_time,
            games.session_state, games.game_state, games.game_visibility, games.players_active, games.players_past_hour,
            '".ServerManager::getInstance()->getServerURLBySessionId()."' AS game_server_address,
            '".ServerManager::getInstance()->getWsServerURLBySessionId()."' AS game_ws_server_address,
            watchdogs.name AS watchdog_name, watchdogs.address AS watchdog_address, games.save_id, 
            CASE
                WHEN games.save_id > 0 THEN 0
                ELSE config_versions.version
            END AS config_version_version, 
            CASE
                WHEN games.save_id > 0 THEN ''
                ELSE config_versions.version_message 
            END AS config_version_message,
            CASE
                WHEN games.save_id > 0 THEN ''
                ELSE config_files.description 
            END AS config_file_description, 
            CASE
            	WHEN games.save_id > 0 THEN saves.game_config_files_filename
                ELSE config_files.filename
            END AS config_file_name,
            CASE
            	WHEN games.save_id > 0 THEN saves.game_config_versions_region
                ELSE config_versions.region
            END AS region,
            DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year as char),'-01-01') , '%Y-%m-%d'),
            INTERVAL + games.game_current_month MONTH),'%M %Y' ) as current_month_formatted, 
            DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year  as char),'-01-01') , '%Y-%m-%d'),
            INTERVAL + games.game_end_month MONTH), '%M %Y' ) as end_month_formatted",
            'game_list AS games
            LEFT JOIN game_servers AS servers ON games.game_server_id = servers.id
            LEFT JOIN game_watchdog_servers AS watchdogs ON games.watchdog_server_id = watchdogs.id
            LEFT JOIN game_config_version AS config_versions ON games.game_config_version_id = config_versions.id
            LEFT JOIN game_config_files AS config_files ON config_versions.game_config_files_id = config_files.id
            LEFT JOIN game_saves AS saves ON saves.id = games.save_id',
            $where_array
        )) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $results = $this->db->results(true);
        foreach ($results as $row => $result) {
            $game = new \App\Domain\API\v1\Game();
            $game->setGameSessionId($result['id']);
            $gameConfig = $game->Config();
            $results[$row]['edition_name'] = $gameConfig['edition_name'];
            $results[$row]['edition_colour'] = $gameConfig['edition_colour'];
            $results[$row]['edition_letter'] = $gameConfig['edition_letter'];
        }

        return $results;
    }

    private function mustUpdateBogusList(): array
    {
        $return[0] = ['id' => 0,
                        'name' => 'Version incompatible with this server.',
                        'session_state' => 'archived',
                        'region' => 'none', ];
        $return[1] = ['id' => 0,
                        'name' => 'Download '.ServerManager::getInstance()->getCurrentVersion().
                            ' from mspchallenge.info.',
                        'session_state' => 'archived',
                        'region' => 'none', ];

        return $return;
    }

    public function getArchive(): bool|string
    {
        if ('archived' != $this->session_state) {
            return false;
        }
        $file = ServerManager::getInstance()->getSessionArchiveBaseDirectory().
            ServerManager::getInstance()->getSessionArchivePrefix();
        $file .= $this->id.'.zip';
        if (file_exists($file)) {
            return $file;
        }

        return false;
    }

    public function getPrettyVars(): array
    {
        $date_current_month = new DateTime($this->game_start_year.'-01-01');
        $date_end_month = new DateTime($this->game_start_year.'-01-01');
        $return['game_start_year'] = $date_current_month->format('M Y');
        if ($this->game_current_month >= 0) {
            $date_current_month->add(new DateInterval('P' . $this->game_current_month . 'M'));
        }
        $return['game_current_month'] = $date_current_month->format('M Y');
        $date_end_month->add(new DateInterval('P'.$this->game_end_month.'M'));
        $return['game_end_month'] = $date_end_month->format('M Y');
        $return['game_creation_time'] = date('j M Y - H:i', $this->game_creation_time);
        $return['game_running_til_time'] = date('j M Y - H:i', $this->game_running_til_time);

        return $return;
    }

    public function upgrade(): bool
    {
        $upgrade = ServerManager::getInstance()->checkForUpgrade($this->server_version);
        if (false !== $upgrade) {
            $server_call = self::callServer(
                'update/'.$upgrade,
                [],
                $this->id,
                $this->api_access_token
            );
            if (!$server_call['success']) {
                throw new ServerManagerAPIException($server_call['message']);
            }
            $this->server_version = ServerManager::getInstance()->getCurrentVersion();

            return true;
        }
        throw new ServerManagerAPIException('No upgrade available.');
    }

    public function delete(): bool
    {
        // really a soft delete   // revert() is a hard delete
        $this->get();
        if ('archived' == $this->session_state) {
            throw new ServerManagerAPIException('The session is already archived.');
        }
        if ('request' == $this->session_state) {
            throw new ServerManagerAPIException('The session is being set up, so cannot archive at this time.');
        }
        if ('simulation' == $this->game_state) {
            throw new ServerManagerAPIException('The session is simulating, so cannot archive it at this time.');
        }
        $server_call = self::callServer(
            'GameSession/ArchiveGameSession',
            ['response_url' => ServerManager::getInstance()->getAbsoluteUrlBase().'api/editGameSession.php'],
            $this->id,
            $this->api_access_token
        );
        if (!$server_call['success']) {
            throw new ServerManagerAPIException($server_call['message'] ?? 'unknown error');
        }
        $this->session_state = 'archived';
        $this->edit();

        return true;
    }

    public function getConfigWithPlans(): array
    {
        $server_call = self::callServer(
            'Plan/ExportPlansToJson',
            [],
            $this->id,
            $this->api_access_token
        );
        if (!$server_call['success']) {
            throw new ServerManagerAPIException($server_call['message']);
        }

        $gameconfig = new GameConfig();
        $gameconfig->id = $this->game_config_version_id;
        $gameconfig->get();
        $configFileDecoded = $gameconfig->getContents();
        if (isset($configFileDecoded['datamodel'])) {
            $configFileDecoded['datamodel']['plans'] = $server_call['payload'];
        } else {
            $configFileDecoded['plans'] = $server_call['payload'];
        }
        $file_contents = json_encode($configFileDecoded, JSON_PRETTY_PRINT);

        // to be used by downloader.php
        return [basename($gameconfig->file_path, '.json').'_With_Exported_Plans.json', $file_contents];
    }

    public function processZip(): bool
    {
        if (isset($_POST['zippath']) && is_file($_POST['zippath'])) {
            $outputDirectory = ServerManager::getInstance()->getSessionArchiveBaseDirectory();
            $storeFilePath = $outputDirectory.basename($_POST['zippath']);
            rename($_POST['zippath'], $storeFilePath);

            return true;
        }

        return false;
    }

    public function changeGameState(): bool
    {
        if (null === $this->old) {
            throw new ServerManagerAPIException("Can't continue as I don't have the old GameSession object.");
        }
        if (0 == strcasecmp($this->old->game_state, $this->game_state)) {
            throw new ServerManagerAPIException('The session is already in state '.$this->game_state.'.');
        }
        switch ($this->old->game_state) {
            case 'end':
                throw new ServerManagerAPIException("The session has already ended, so can't change its state.");
            case 'simulation':
                throw new ServerManagerAPIException(
                    'The session is simulating, so cannot change its state at this time.'
                );
        }
        $server_call = self::callServer(
            'Game/State',
            ['state' => $this->game_state],
            $this->id,
            $this->api_access_token
        );
        if (empty($server_call['success'])) {
            throw new ServerManagerAPIException($server_call['message'] ?? '');
        }

        return true;
    }

    private function logSessionCreation(): void
    {
        $analyticsLogger = null;
        try {
            $legacyHelper = SymfonyToLegacyHelper::getInstance();
            $analyticsLogger = $legacyHelper->getAnalyticsLogger();

            $gameConfig = new GameConfig();
            $gameConfig->id = $this->game_config_version_id;
            $gameConfig->get();

            $gameConfigContents = $gameConfig->getContents();
            $gameStartYear = -1;
            $gameEndMonth = -1;
            if (isset($gameConfigContents["datamodel"])) {
                $gameStartYear = $gameConfigContents["datamodel"]["start"] ?? -1;
                $endYear = $gameConfigContents["datamodel"]["end"] ?? -1;
                $validStartAndEnd = $gameStartYear > 0 && $endYear > 0 && $endYear > $gameStartYear;
                $gameEndMonth =  $validStartAndEnd ? ($endYear - $gameStartYear) * 12 : -1;
            }

            $tempImmutableDateTime = new DateTimeImmutable();

            $gameCreationTimeStamp = $this->game_creation_time ? intval($this->game_creation_time) : 0;
            $gameCreationTime = $tempImmutableDateTime->setTimestamp($gameCreationTimeStamp);

            $serverManager = ServerManager::getInstance();
            $serverManagerId = Uuid::fromString($serverManager->getServerUuid());

            $userId = $_SESSION['user'];
            $userData = is_null($userId) ? null : (new User($userId))->data();

            $analyticsMessage = new SessionCreatedMessage(
                new DateTimeImmutable(),
                $serverManagerId,
                $userData?->username ?? '',
                $userData?->account_id ?? -1,
                $this->id,
                $this->name,
                $gameCreationTime,
                $gameStartYear,
                $gameEndMonth,
                $gameConfig->filename,
                $gameConfig->version,
                $gameConfig->version_message,
                $gameConfig->region,
                $gameConfig->description
            );
            $legacyHelper->getAnalyticsMessageBus()->dispatch($analyticsMessage);
        } catch (Exception $e) {
            $analyticsLogger?->error(
                "Exception occurred while dispatching game session creation message: ".
                $e->getMessage()
            );
        }
    }
}
