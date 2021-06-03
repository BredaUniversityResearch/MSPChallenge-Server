<?php

class GameSession extends Base
{
    private $_db;
    private $_old;

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
        $this->_db = DB::getInstance();
    }

    private function validateVars()
    {
        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $var)
        {   
            $varname = $var->getName();
            $temp[] = $varname;
            switch ($varname)
            {
                case "password_player":
                case "password_admin":
                    $this->$varname = $this->CheckPasswordFormat($varname, $this->$varname);
                    break;
                case "name":
                    if (self::HasSpecialChars($this->name)) throw new Exception("Session name cannot contain special characters.");
                    break;
                case "session_state":
                    if (!in_array($this->session_state, array("request", "initializing", "healthy", "failed", "archived"))) throw new Exception("That session state is not allowed.");
                    break;
                case "game_state":
                    $this->game_state = strtolower($this->game_state);
                    if (!in_array($this->game_state, array("setup", "simulation", "play", "pause", "end", "fastforward"))) throw new Exception("That game state is not allowed.");
                    break;
                case "log":
                    break; // ignoring, because not stored in dbase
                default:
                    if (strlen($this->$varname) == 0) throw new Exception("Missing value for ".$varname);
            }
        }
    }

    public function get()
    {
        if (empty($this->id)) throw new Exception("Cannot obtain GameSession without a valid id.");
        if (!$this->_db->query("SELECT * FROM game_list WHERE id = ?", array($this->id))) throw new Exception($this->_db->errorString());
        if ($this->_db->count() == 0) throw new Exception("Session not found.");
        foreach ($this->_db->first(true) as $varname => $varvalue)
        {
            if (property_exists($this, $varname)) $this->$varname = $varvalue;
        }

        // backwards compatibility (beta7 and earlier), when the password fields were unencoded strings
        if (base64_encoded($this->password_admin)) $this->password_admin = base64_decode($this->password_admin);
        $this->password_admin = $this->CheckPasswordFormat("password_admin", $this->password_admin);
        if (base64_encoded($this->password_player)) $this->password_player = base64_decode($this->password_player);
        $this->password_player = $this->CheckPasswordFormat("password_player", $this->password_player);

        $log_dir = ServerManager::getInstance()->GetSessionLogBaseDirectory();
        $log_path = $log_dir . ServerManager::getInstance()->GetSessionLogPrefix() . $this->id . ".log";
        if (file_exists($log_path)) 
        {
            $log_contents = file_get_contents($log_path);
            if ($log_contents === false) $this->log = "Session log does not exist (yet).";
            else $this->log = explode(PHP_EOL, rtrim($log_contents));
        }
        $this->_old = clone $this;
    }

    public function add() 
    {
        $this->validateVars();
        $args = array();
        $sql = "INSERT INTO `game_list` 
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $args = getPublicObjectVars($this);
        $args["password_admin"] = base64_encode($args["password_admin"]);
        $args["password_player"] = base64_encode($args["password_player"]);
        unset($args["id"]);
        unset($args["log"]);
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
        $this->id = $this->_db->lastId();
    }

    public function sendCreateRequest($allow_recreate = 0)
    {
        $geoserver = new GeoServer;
        $geoserver->setJWT($this->getJWT());
        $geoserver->id = $this->game_geoserver_id;
        $geoserver->get();

        $gameconfig = new GameConfig;
        $gameconfig->id = $this->game_config_version_id;
        $gameconfig->get();

        $watchdog = new Watchdog;
        $watchdog->id = $this->watchdog_server_id;
        $watchdog->get();

        $server_call = self::callServer(
            "GameSession/CreateGameSession", 
            array(
                "game_id" => $this->id,
                "config_file" => $gameconfig->getFile(),
                "geoserver_url" => $geoserver->address,
                "geoserver_username" => $geoserver->username,
                "geoserver_password" => $geoserver->password,
                "password_admin" => base64_encode($this->password_admin),
                "password_player" => base64_encode($this->password_player),
                "watchdog_address" => $watchdog->address,
                "allow_recreate" => $allow_recreate,
                "response_address" => ServerManager::getInstance()->GetFullSelfAddress()."api/editGameSession.php"
            )
        );
        if (!$server_call["success"]) 
        {
            if ($allow_recreate == 0) $this->revert();
            throw new Exception($server_call["message"]);
        }

        $gameconfig->last_played_time = time();
        $gameconfig->edit();

        $authoriser_call = self::callAuthoriser(
            "logcreatejwt.php", 
            array(
                "jwt" => $this->getJWT(), 
                "audience" => ServerManager::getInstance()->GetBareHost(),
                "server_id" => ServerManager::getInstance()->GetServerID(),
                "region" => $gameconfig->region,
                "session_id" => $this->id
            )
        );
    }

    public function recreate()
    {
        if ($this->save_id > 0) return $this->reload();
        
        $this->sendCreateRequest(1);
        $this->setToLoading();
        return true;
    }

    private function reload()
    {
        $gamesave = new GameSave;
        $gamesave->id = $this->save_id;
        $gamesave->get();

        $this->game_start_year = $gamesave->game_start_year;
        $this->game_end_month = $gamesave->game_end_month;
        $this->game_current_month = $gamesave->game_current_month;
        $this->password_admin = $gamesave->password_admin;
        $this->password_player = $gamesave->password_player;
        $this->game_state = $gamesave->game_state;
        $this->game_visibility = $gamesave->game_visibility;
        $this->players_active = $gamesave->players_active;
        $this->players_past_hour = $gamesave->players_past_hour;
        $this->demo_session = $gamesave->demo_session;
        $this->api_access_token = $gamesave->api_access_token;
        $this->server_version = $gamesave->server_version;

        $watchdog = new Watchdog;
        $watchdog->id = $this->watchdog_server_id;
        $watchdog->get();

        $server_call = self::callServer(
            "GameSession/LoadGameSave", 
            array(
                "save_path" => $gamesave->getFullZipPath(),
                "watchdog_address" => $watchdog->address,
                "game_id" => $this->id,
                "response_address" => ServerManager::getInstance()->GetFullSelfAddress()."api/editGameSession.php",
                "allow_recreate" => 1
            )
        );
        if (!$server_call["success"]) throw new Exception($server_call["message"]);
        $this->setToLoading();
        return true;
    }

    private function setToLoading()
    {
        $now = time();
        $this->session_state = "request";
        $this->game_creation_time = $now;
        $this->game_running_til_time = $now;
    }

    private function revert()
    {
        $this->_db->query("DELETE FROM game_list WHERE id = ?;", $this->id);
    }

    public function demoCheck()
    {
        if (!is_a($this->_old, "GameSession")) throw new Exception("Cannot continue as I don't have original GameSession object.");

        if ($this->demo_session == 1) 
        {
            if ($this->_old->session_state == "healthy" && ($this->_old->game_state == "pause" || $this->_old->game_state == "setup")) 
            {
                // healthy demo sessions need to return to play if previously set to pause or setup
                $this->game_state = "play";
                $this->changeGameState();
            }
            elseif ($this->_old->session_state == "healthy" && $this->_old->game_state == "end")
            {
                // demo sessions need to be recreated if they had ended previously
                $this->recreate();
            }
        }
        return true;
    }

    public function edit()
    {
        if (empty($this->id)) throw new Exception("Cannot update without knowing which id to use.");
        $this->validateVars();
        $args = getPublicObjectVars($this);
        $args["password_admin"] = base64_encode($args["password_admin"]);
        $args["password_player"] = base64_encode($args["password_player"]);
        unset($args["log"]);
        $sql = "UPDATE game_list SET 
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
                WHERE id = ?";
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
    }

    private function CheckPasswordFormat($adminorplayer, $string)
    {
        if (isJson($string)) 
        {
            // backwards compatibility
            $string_decoded = json_decode($string, true);
            if (isset($string_decoded["admin"])) {
                if (isset($string_decoded["admin"]["password"])) {
                    $string_decoded["admin"]["value"] = $string_decoded["admin"]["password"];
                    unset($string_decoded["admin"]["password"]);
                }
                elseif (isset($string_decoded["admin"]["users"])) {
                    if (is_array($string_decoded["admin"]["users"])) $string_decoded["admin"]["users"] = implode(" ", $string_decoded["admin"]["users"]);
                    $string_decoded["admin"]["value"] = $string_decoded["admin"]["users"];
                    unset($string_decoded["admin"]["users"]);
                }
                if (isset($string_decoded["region"]["password"])) {
                    $string_decoded["region"]["value"] = $string_decoded["region"]["password"];
                    unset($string_decoded["region"]["password"]);
                }
                elseif (isset($string_decoded["region"]["users"])) {
                    if (is_array($string_decoded["region"]["users"])) $string_decoded["region"]["users"] = implode(" ", $string_decoded["region"]["users"]);
                    $string_decoded["region"]["value"] = $string_decoded["region"]["users"];
                    unset($string_decoded["region"]["users"]);
                }
            }
            elseif (isset($string_decoded["password"])) {
                $string_decoded["value"] = $string_decoded["password"];
                unset($string_decoded["password"]);
            }
            elseif (isset($string_decoded["users"])) {
                if (is_array($string_decoded["users"])) $string_decoded["users"] = implode(" ", $string_decoded["users"]);
                $string_decoded["value"] = $string_decoded["users"];
                unset($string_decoded["users"]);
            }
            return json_encode($string_decoded);
        }
        
        // only used once when creating new session
        if ($adminorplayer == "password_admin") 
        {
            $newarray["admin"]["provider"] = "local";
            $newarray["admin"]["value"] = $string;
            $newarray["region"]["provider"] = "local";
            $newarray["region"]["value"] = $string;
        } 
        else 
        {
            $newarray["provider"] = "local";
            $countries = $this->getCountries();
            if ($countries !== false) {
                foreach ($countries as $country_data) {
                    $newarray["value"][$country_data["country_id"]] = $string;
                }
            }
        }
        return json_encode($newarray);
    }

    public function getCountries() 
    { // using config files rather than the session database for this, as this function can be called pre session existence
        if (!empty($this->save_id)) // session eminates from a save as save_id is neither null nor 0
        {
            $gamesave = new GameSave;
            $gamesave->id = $this->save_id;
            $gamesave->get();
            $configData = $gamesave->getContentsConfig();
        }
        else // session eminates from a config file, so from scratch
        {
            if (empty($this->game_config_version_id)) throw new Exception("Cannot obtain GameConfig without a valid id.");
            $gameconfig = new GameConfig;
            $gameconfig->id = $this->game_config_version_id;
            $gameconfig->get();
            $configData = $gameconfig->getContents();
        }        
        
        $countries = array();
        foreach($configData['datamodel']['meta'] as $layerMeta) 
        {
            if ($layerMeta['layer_name'] == $configData['datamodel']['countries'])
            {
                foreach($layerMeta['layer_type'] as $country)
                {
                    $tempvalue["country_id"] = $country['value'];
                    $tempvalue["country_name"] = $country['displayName'];
                    $tempvalue["country_colour"] = $country['polygonColor'];
                    $countries[] = $tempvalue;
                }
            }
        }
        return $countries;
    }

    public function setUserAccess()
    {
        $server_call = self::callServer(
            "gamesession/SetUserAccess", 
            array(
                "password_admin" => base64_encode($this->password_admin), 
                "password_player" => base64_encode($this->password_player)
            ),
            $this->id, 
            $this->api_access_token
        );
        if (!$server_call["success"]) throw new Exception($server_call["message"]);
        return true;
    }

    public function getList($where_array=array())
    {
        if (!$this->_db->action(
            "SELECT games.id, games.name, games.game_config_version_id, games.game_server_id, games.watchdog_server_id,
            games.game_creation_time, games.game_start_year, games.game_end_month, games.game_current_month, games.game_running_til_time,
            games.session_state, games.game_state, games.game_visibility, games.players_active, games.players_past_hour,
            '".ServerManager::getInstance()->GetServerURLBySessionId()."' AS game_server_address,
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
            DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_current_month MONTH),'%M %Y' ) as current_month_formatted, 
            DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year  as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_end_month MONTH), '%M %Y' ) as end_month_formatted",
            "game_list AS games
            LEFT JOIN game_servers AS servers ON games.game_server_id = servers.id
            LEFT JOIN game_watchdog_servers AS watchdogs ON games.watchdog_server_id = watchdogs.id
            LEFT JOIN game_config_version AS config_versions ON games.game_config_version_id = config_versions.id
            LEFT JOIN game_config_files AS config_files ON config_versions.game_config_files_id = config_files.id
            LEFT JOIN game_saves AS saves ON saves.id = games.save_id", 
            $where_array
        )) throw new Exception($this->_db->errorString());
        return $this->_db->results(true);
    }

    public function getArchive()
    {
        if ($this->session_state != "archived") return false;
        $file = ServerManager::getInstance()->GetSessionArchiveBaseDirectory().ServerManager::getInstance()->GetSessionArchivePrefix();
        $file .= $this->id.".zip";
        if (file_exists($file)) {
            return $file;
        }
        return false;
    }

    public function getPrettyVars()
    {
        $date_current_month = new DateTime($this->game_start_year."-01-01");
        $date_end_month = new DateTime($this->game_start_year."-01-01");
        $return["game_start_year"] = $date_current_month->format("M Y");
        $date_current_month->add(new DateInterval('P'.$this->game_current_month.'M'));
        $return["game_current_month"] = $date_current_month->format("M Y");
        $date_end_month->add(new DateInterval('P'.$this->game_end_month.'M'));
        $return["game_end_month"] = $date_end_month->format("M Y");
        $return["game_creation_time"] = date("j M Y - H:i", $this->game_creation_time);
        $return["game_running_til_time"] = date("j M Y - H:i", $this->game_running_til_time);
        return $return;
    }

    public function upgrade()
    {
        $upgrade = ServerManager::getInstance()->CheckForUpgrade($this->server_version);
        if ($upgrade !== false) {
            $server_call = self::callServer(
                "update/".$upgrade, 
                array(),
                $this->id, 
                $this->api_access_token
            );
            if (!$server_call["success"]) throw new Exception($server_call["message"]);
            $this->server_version = ServerManager::getInstance()->GetCurrentVersion();
            return true;
        }
        throw new Exception("No upgrade available.");
    }

    public function delete()
    {
        // really a soft delete   // revert() is a hard delete
        $this->get();
        if ($this->session_state == "archived") 
            throw new Exception("The session is already archived.");
        if ($this->session_state == "request")
            throw new Exception("The session is being set up, so cannot archive at this time.");
        if ($this->game_state == "simulation") 
            throw new Exception("The session is simulating, so cannot archive it at this time.");
        $server_call = self::callServer(
            "gamesession/ArchiveGameSession", 
            array("response_url" => ServerManager::getInstance()->GetFullSelfAddress()."api/editGameSession.php"),
            $this->id, 
            $this->api_access_token
        );
        if (!$server_call["success"]) throw new Exception($server_call["message"]);
        $this->session_state = "archived";
        $this->edit();
        return true;
    }

    public function getConfigWithPlans()
    {
        $server_call = self::callServer(
            "plan/ExportPlansToJson", 
            array(),
            $this->id, 
            $this->api_access_token
        );
        if (!$server_call["success"]) throw new Exception($server_call["message"]);

        $gameconfig = new GameConfig;
        $gameconfig->id = $this->game_config_version_id;
        $gameconfig->get();
        $configFileDecoded = $gameconfig->getContents();
        if (isset($configFileDecoded->datamodel)) $configFileDecoded["datamodel"]["plans"] = $server_call["payload"];
        else $configFileDecoded["plans"] = $server_call["payload"];
        $file_contents = json_encode($configFileDecoded, JSON_PRETTY_PRINT);
        return array(basename($gameconfig->file_path, ".json")."_With_Exported_Plans.json", $file_contents); // to be used by downloader.php
    }

    public function processZip()
    {
        if(isset($_POST['zippath']) && is_file($_POST['zippath'])) 
        {
            $outputDirectory = ServerManager::getInstance()->GetSessionArchiveBaseDirectory();
            $storeFilePath = $outputDirectory.basename($_POST['zippath']);
            rename($_POST['zippath'], $storeFilePath);
            return true;
        }
        return false;
    }

    public function changeGameState()
    {
        if (!is_a($this->_old, "GameSession")) throw new Exception("Can't continue as I don't have the old GameSession object.");
        if (strcasecmp($this->_old->game_state, $this->game_state) == 0) 
            throw new Exception("The session is already in state ".$this->game_state.".");
        switch ($this->_old->game_state) {
            case 'end':
                throw new Exception("The session has already ended, so can't change its state.");
                break;
            case 'setup':
                if ($this->game_state != "play") throw new Exception("The session is in setup, so only play is available at this time.");
                break;
            case 'simulation':
                throw new Exception("The session is simulating, so cannot change its state at this time.");
                break;
        }
        $server_call = self::callServer(
            "game/State", 
            array("state" => $this->game_state),
            $this->id, 
            $this->api_access_token
        );
        if (!$server_call["success"]) throw new Exception($server_call["message"]);
        return true;
    }
}