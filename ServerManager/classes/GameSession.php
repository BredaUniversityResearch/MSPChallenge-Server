<?php

class GameSession extends Base
{
    private $_db;
    private $_id;
    private $_list;

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
    
    public function __construct() 
    {
        $this->_db = DB::getInstance();
    }

    public function getId()
    {
        return $this->_id;
    }

    private function setList($list)
    {
        if (!is_array($list)) $list = array();
        $this->_list = array("sessionslist" => $list);
    }

    public function get($id)
    {
        if (empty($id)) return "Cannot obtain data without a valid id.";
        $this->_id = $id;
        try
        {
            if ($this->_db->query("SELECT * FROM game_list WHERE id = ?", array($this->_id))) 
            {
                foreach ($this->_db->first(true) as $varname => $varvalue)
                {
                    if ($varname == "password_player" || $varname == "password_admin") {
                        $varvalue = $this->ConvertPasswordFormat($varname, $varvalue);
                    }
                    if (property_exists($this, $varname)) $this->$varname = $varvalue;
                }
                return true;
            } else {
                return $this->_db->errorString();
            }
        } catch (Exception $e)
        {
            return $e->getMessage();
        }
    }

    private function ConvertPasswordFormat($adminorplayer, $string)
    {
        // if base64 encoding check works, and the decoded version turns out to be json, then all is good, otherwise...
        if (base64_encode(base64_decode($string, true)) === $string) {
            if (isJson(base64_decode($string))) {
                return base64_decode($string);
            }
        }
            
        if ($adminorplayer == "password_admin") {
            $newarray["admin"]["provider"] = "local";
            $newarray["admin"]["password"] = $string;
            $newarray["region"]["provider"] = "local";
            $newarray["region"]["password"] = $string;
        } else {
            $newarray["provider"] = "local";
            $countries = $this->getCountries();
            if ($countries !== false) {
                foreach ($countries as $country_data) {
                    $newarray["password"][$country_data["country_id"]] = $string;
                }
            }
        }
        return json_encode($newarray);
    }

    public function getCountries()
    {
        if (empty($this->_id)) return "Cannot obtain data without a valid id.";
        $server_call = $this->callServer(
            "game/getCountries",
            false,
            $this->_id
        );
        if ($server_call["success"]) {
            return $server_call["payload"];
        }
        return false;
    }

    public function getSanitised($id)
    {
        // basically getsessioninfo.php
    }

    public function create() 
    {
        // copy and adjust from creatnewsession endpoint
    }

    private function validateVars()
    {
        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $var)
        {   
            $varname = $var->getName();
            if (strlen($this->$varname) == 0) return "Missing value for ".$varname;
        }
        return true;
    }

    public function update()
    {
        $validated = $this->validateVars();
        if ($validated !== true) return $validated;
        if (empty($this->_id)) return "Cannot update with knowing which id to use.";
        try
        {
            $args = array(
                $this->name,
                $this->game_config_version_id,
                $this->game_server_id,
                $this->game_geoserver_id,
                $this->watchdog_server_id,
                $this->game_creation_time,
                $this->game_start_year,
                $this->game_end_month,
                $this->game_current_month,
                $this->game_running_til_time,
                $this->password_admin,
                $this->password_player,
                $this->session_state,
                $this->game_state,
                $this->game_visibility,
                $this->players_active,
                $this->players_past_hour,
                $this->demo_session,
                $this->api_access_token,
                $this->save_id,
                $this->server_version,
                $this->_id);
            if ($this->_db->query("UPDATE game_list SET 
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
                WHERE id = ?", $args))
            {
                return true;
            } else {
                return $this->_db->errorString();
            }
        } catch (Exception $e)
        {
            return $e->getMessage();
        }
    }

    public function sync()
    {
        // copy and adjust from serverlistupdater class >> get list of sessions, for each call update()
        // >> with an check if it's a demo, and if so, and it's set to "end" then recreate
    }

    public function recreate()
    {
        // copy and adjust from recreatesession endpoint
    }

    public function setUserAccess($id, $admin, $player)
    {
        $got = $this->get($id);
        if ($got !== true) return $got;
        if (is_array($admin) && is_array($player))
        {
            if (!empty($admin["admin"]) && is_array($admin["admin"]) && !empty($admin["region"]) && is_array($admin["region"]))
            {
                $this->password_admin = base64_encode(json_encode($admin));
                $this->password_player = base64_encode(json_encode($player));
                $updated = $this->update();
                if ($updated !== true) return $updated;
                $server_call = $this->callServer(
                    "gamesession/SetUserAccess", 
                    array("password_admin" => $this->password_admin, "password_player" => $this->password_player),
                    $this->_id, 
                    $this->api_access_token
                );
                if ($server_call["success"] !== true) return $server_call["message"];
                return true;
            }
            return "Admin password variable incorrectly structured.";
        } 
        return "Input variables need to be arrays.";
    }

    public function getList()
    {
        // basically getsessionslist.php
        $query_string = "";
        $where_array = "";
        
        try 
        {
            if ($this->_db->query($query_string, $where_array)) 
            {
                $this->setList($this->_db->results(true));
                return $this->_list;
            } else {
                return $this->_db->errorString();
            }
        } catch (Exception $e) 
        {
            return $e->getMessage();
        }
    }
}