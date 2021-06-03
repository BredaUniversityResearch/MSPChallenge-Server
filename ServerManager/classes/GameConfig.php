<?php

class GameConfig extends Base
{
    private $_db;
    private $_old;
    private $_upload_temp_path;

    public $filename;
    public $description;
    public $version;
    public $version_message;
    public $visibility;
    public $upload_time;
    public $upload_user;
    public $last_played_time;
    public $file_path;
    public $region;
    public $client_versions;
    public $game_config_files_id;
    public $id; // version id
    
    public function __construct() 
    {
        $this->_db = DB::getInstance();
    }

    private function validateVars()
    {
        if (strlen($this->filename) == 0) throw new Exception("Filename cannot be empty.");
        $this->filename = strip_tags($this->filename);
		$this->filename = str_replace(" ", "_", $this->filename);
		$this->filename = preg_replace( '/[^a-zA-Z0-9\_]+/', '-', $this->filename);
        if (strlen($this->description) == 0) throw new Exception("Description cannot be empty.");
        $this->version = intval($this->version);
        if (strlen($this->version_message) == 0) throw new Exception("Version message cannot be empty.");
        if ($this->visibility != "active" && $this->visibility != "archived") throw new Exception("Visibility needs to be active or archived only.");
        $this->upload_user = intval($this->upload_user);
        $this->upload_time = intval($this->upload_time);
        $this->last_played_time = intval($this->last_played_time);
    }

    public function get()
    {
        if (!empty($this->id)) {
            if (!$this->_db->query("SELECT gcv.*, gcf.filename, gcf.description 
                                    FROM game_config_version gcv 
                                    INNER JOIN game_config_files gcf ON gcv.game_config_files_id = gcf.id 
                                    WHERE gcv.id = ?;", array($this->id))) throw new Exception($this->_db->errorString());
            if ($this->_db->count() == 0) throw new Exception("Config file not found.");
        }
        elseif (!empty($this->game_config_files_id)) {
            if (!$this->_db->query("SELECT gcv.*, gcf.filename, gcf.description 
                                    FROM game_config_files gcf 
                                    INNER JOIN game_config_version gcv ON gcv.game_config_files_id = gcf.id 
                                    WHERE gcf.id = ?
                                    ORDER BY gcv.version DESC
                                    LIMIT 1;", array($this->game_config_files_id))) throw new Exception($this->_db->errorString());
            if ($this->_db->count() == 0) throw new Exception("Config file not found.");
        }
        else throw new Exception("Cannot obtain GameConfig without a valid id.");
        foreach ($this->_db->first(true) as $varname => $varvalue)
        {
            if (property_exists($this, $varname)) $this->$varname = $varvalue;
        }
    }

    public function getFile()
    {
        $path = ServerManager::getInstance()->GetConfigBaseDirectory().$this->file_path;
        if (file_exists($path)) return $path;
        return false;
    }

    public function getContents()
    {
        return json_decode(file_get_contents($this->getFile()), true);
    }

    public function getPrettyVars()
    {
        $return["upload_time"] = date("j M Y - H:i", $this->upload_time);
        $return["last_played_time"] = ($this->last_played_time == 0) ? "Never" : date("j M Y - H:i", $this->last_played_time);
        $return["upload_user"] = ($this->upload_user == 1) ? "BUas (at installation)" : (new User($this->upload_user))->data()->username;
        return $return;
    }

    private function getValuesFromConfigContents()
    {
        $config_contents = $this->getContents();
        $this->region = $configFileValues["datamodel"]["region"] ?? "Unknown";
        $min = $config_contents["metadata"]["min_supported_client"] ?? "Any";
        $max = $config_contents["metadata"]["max_supported_client"] ?? "Any";
		$this->client_versions = ($min != "Any" || $max != "Any") ? $min." - ".$max : "Any";
    }

    public function add() 
    {
        $this->validateVars();

        $this->file_path = $this->filename."/".$this->filename."_".$this->version.".json";
        if (is_file(ServerManager::getInstance()->GetConfigBaseDirectory().$this->file_path)) 
            throw new Exception("Cannot store that file as it already exists.");
        $outputDirectory = pathinfo(ServerManager::getInstance()->GetConfigBaseDirectory().$this->file_path, PATHINFO_DIRNAME);
        if (!is_dir($outputDirectory)) mkdir($outputDirectory, 0777); 
        if (!move_uploaded_file($this->_upload_temp_path, ServerManager::getInstance()->GetConfigBaseDirectory().$this->file_path))
            throw new Exception("Could not put the config file in its proper place.");

        $this->getValuesFromConfigContents();

        if ($this->game_config_files_id == -1) $this->addFirstFile();

        $sql = "INSERT INTO `game_config_version` 
                (   version, 
                    version_message,
                    visibility,
                    upload_time, 
                    upload_user,
					last_played_time, 
                    file_path, 
                    region, 
                    client_versions, 
					game_config_files_id
                )
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $args = getPublicObjectVars($this);
        unset($args["id"]);
        unset($args["filename"]);
        unset($args["description"]);
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
        $this->id = $this->_db->lastId();        
    }

    public function uploadTemp($path)
    {
        if (!is_file($path)) throw new Exception("Nothing seems to have been uploaded.");
        $this->_upload_temp_path = $path;
    }
    
    private function addFirstFile()
    {
        if (!$this->_db->query("INSERT INTO game_config_files (filename, description) VALUES(?, ?)", array($this->filename, $this->description)))
            throw new Exception($this->_db->errorString());
        $this->game_config_files_id = $this->_db->lastId();
    }

    public function edit()
    {
        if (empty($this->id)) throw new Exception("Cannot update with knowing which id to use.");
        $this->validateVars();
        $args = getPublicObjectVars($this);
        $sql = "UPDATE game_config_files gcf, game_config_version gcv SET 
                    gcf.filename = ?,
                    gcf.description = ?,
                    gcv.version = ?,
                    gcv.version_message = ?,
                    gcv.visibility = ?,
                    gcv.upload_time = ?,
                    gcv.upload_user = ?,
                    gcv.last_played_time = ?,
                    gcv.file_path = ?,
                    gcv.region = ?,
                    gcv.client_versions = ?
                WHERE gcf.id = ?
                AND gcv.id = ?";
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
        return true;
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->visibility = ($this->visibility == "active") ? "archived" : "active";
        $this->edit();
    }

    public function getList($where_array)
    {
        if (!$this->_db->get("game_config_files", array(), "filename ASC"))throw new Exception($this->_db->errorString());
        $return_array = $this->_db->results(true);
        foreach ($return_array as $row => $gameconfig) {
            $total_where_array = array("AND", $where_array, array("game_config_files_id", "=", $gameconfig["id"]));
            $this->_db->get("game_config_version", $total_where_array, "upload_time DESC");
            $results = $this->_db->results(true);
            if (count($results) > 0) {
                foreach ($results as $row2 => $gameconfig2) {
                    $this->upload_time = $gameconfig2["upload_time"];
                    $this->last_played_time = $gameconfig2["last_played_time"];
                    $this->upload_user = $gameconfig2["upload_user"];
                    $pretty_vars = $this->getPrettyVars();
                    $results[$row2]["pretty"] = $pretty_vars;
                }
                $return_array[$row]["all_versions"] = $results;
            }
            else unset($return_array[$row]);
        }
        return $return_array;
    }
}