<?php

class ServerManager extends Base
{
    private static $_instance = null;
    private $_old, $_db, $_server_id, $_server_versions, $_server_current_version, $_server_root, $_server_manager_root, $_server_upgrades, $_msp_auth_url, $_msp_auth_api;
    public $server_name, $server_address, $server_description;

      public function __construct() {
        $this->_server_versions = array(
          "4.0-beta7",
          "4.0-beta8"
        );
        $this->_server_current_version = end($this->_server_versions);
        $this->_server_upgrades = array(
          "From40beta7To40beta8" // make sure this function exists in server API update class and is actually callable - just letters and numbers of course
        );
        $this->SetRootVars();
        $this->_msp_auth_url = "https://auth.mspchallenge.info";
        $this->_msp_auth_api = "https://auth.mspchallenge.info/usersc/plugins/apibuilder/authmsp/";
      }

      private function CompletePropertiesFromDB() {
        $this->_db = DB::getInstance();
        $this->_server_id = $this->_db->cell("settings.value", array("name", "=", "server_id"));
        $this->server_name = $this->_db->cell("settings.value", array("name", "=", "server_name"));
        $this->server_address = $this->_db->cell('game_servers.address', array("id", "=", 1));
        $this->server_description = $this->_db->cell("settings.value", array("name", "=", "server_description"));

        $this->_old = clone $this;
      }

      private function SetRootVars() {
        $server_root = $_SERVER['DOCUMENT_ROOT'];
        $server_manager_root = '';
        $self_path = explode("/", $_SERVER['PHP_SELF']);
        $self_path_length = count($self_path);
        for($i = 1; $i < $self_path_length; $i++){
          array_splice($self_path, $self_path_length-$i, $i);
          $server_manager_root = implode("/",$self_path)."/";
          if (file_exists($server_root.$server_manager_root.'init.php')) break;
        }
        $this->_server_root = $server_root."/";
        $this->_server_manager_root = ltrim($server_manager_root, "/");
      }

      public static function getInstance() {
        if (!isset(self::$_instance)) {
          self::$_instance = new ServerManager();
        }
        return self::$_instance;
	    }

      public function CheckForUpgrade($versiondetermined) {
        if (!empty($versiondetermined)) {
          // postulate the upgrade function name 
          $upgradefunction = preg_replace('/[^A-Za-z0-9]/', '', "From".$versiondetermined."To".$this->_server_current_version);
          // see if it's in the _server_upgrades list
          if (in_array($upgradefunction, $this->_server_upgrades)) {
            return $upgradefunction;
          }
        }
        // return the upgrade function name or false
        return false;
      }

      public function GetMSPAuthAPI() {
        return $this->_msp_auth_api;
      }

      public function GetMSPAuthURL() {
        return $this->_msp_auth_url;
      }

      public function GetAllVersions() {
        return $this->_server_versions;
      }

      public function GetCurrentVersion() {
        return $this->_server_current_version;
      }
        
      public function GetServerID() {
        if (is_null($this->_server_id)) $this->CompletePropertiesFromDB();
        return $this->_server_id;
      }

      public function GetServerName() {
        if (empty($this->server_name)) $this->CompletePropertiesFromDB();
        return $this->server_name;
      }

      public function freshinstall() {
        if (empty($this->_server_id)) $this->CompletePropertiesFromDB();
        return (empty($this->_server_id));
      }

      public function install($user=null) {
          if ($this->freshinstall()) {
              $this->SetServerID();
              $this->SetServerName($user);
              $this->SetServerDescription();
              return true;
          }
          return false;
      }
      
      public function SetServerAddress($user=null) {
        $this->_db->query("UPDATE game_servers SET address = ? WHERE id = 1;", array($this->server_address));
        return $this->server_address;
      }

      private function SetServerID()  {
        if (empty($this->_server_id)) {
            $this->_server_id = uniqid('', true);
            $this->_db->query("INSERT INTO settings (name, value) VALUES (?, ?);", array("server_id", $this->_server_id));
        }
        return $this->_server_id;
      }

      public function SetServerName($user=null) {
        if (empty($this->server_name)) {
          if (empty($user)) return false;
          $this->server_name =  $user->data()->username . '_' . date("Ymd"); 
        }
        
        if (is_a($this->_old, "ServerManager") && $this->_old->server_name == $this->server_name) return $this->server_name; // no need to do anything if nothing changes

        $try_update = $this->_db->query("UPDATE settings SET value = ? WHERE name = ?;", array($this->server_name, "server_name"));
        if ($try_update && $this->_db->count() == 0) 
        {
          $this->_db->query("INSERT INTO settings (name, value) VALUES (?, ?);", array("server_name", $this->server_name));
        }
        return $this->server_name;
      }

      public function SetServerDescription() {
        if (empty($this->server_description)) {
          $this->server_description =  "This is a new MSP Challenge server installation. The administrator has not changed this default description yet. This can be done through the ServerManager.";
        }

        if (is_a($this->_old, "ServerManager") && $this->_old->server_description == $this->server_description) return $this->server_description; // no need to do anything if nothing changes

        $try_update = $this->_db->query("UPDATE settings SET value = ? WHERE name = ?;", array($this->server_description, "server_description"));
        if ($try_update && $this->_db->count() == 0) 
        {
          $this->_db->query("INSERT INTO settings (name, value) VALUES (?, ?);", array("server_description", $this->server_description));
        }
        return $this->server_description;
      }
      
      public function GetServerURLBySessionId($sessionId="") {
        // e.g. http://localhost/1
        // use this one if you just want the full URL of a Server's session
        $url = Config::get('msp_server_protocol').$this->GetTranslatedServerURL().Config::get('code_branch');
        if (!empty($sessionId)) $url .= "/".$sessionId;
        return $url;
      }
      
      public function GetFullSelfAddress() {
        // e.g. http://localhost/ServerManager/
        // use this one if you just want the full URL of the ServerManager
        return $this->GetBareHost().Config::get('code_branch').$this->_server_manager_root;
      }
      
      public function GetBareHost() {
        // e.g. http://localhost
        return Config::get('msp_servermanager_protocol').$this->GetTranslatedServerURL();
      }
      
      public function GetTranslatedServerURL() {
        if (empty($this->server_address)) $this->CompletePropertiesFromDB();
        // e.g. localhost
        if (!empty($_SERVER['SERVER_NAME'])) {
          if ($_SERVER['SERVER_NAME'] != $this->server_address) {
            return $_SERVER['SERVER_NAME'];
          }
        }  
        return $this->server_address;
      }

      public function GetServerRoot() {
        //e.g. C:/Program Files/MSP Challenge/Server/
        // use this if you just want the folder location of the Server
        return $this->_server_root;
      }

      public function GetServerManagerRoot() {
        //e.g. C:/Program Files/MSP Challenge/Server/ServerManager/
        // use this if you just want the folder location of the ServerManager
        return $this->_server_root.$this->_server_manager_root;
      }

      public function GetServerManagerFolder() {
        // e.g. /ServerManager/
        return "/".$this->_server_manager_root;
      }

      public function GetConfigBaseDirectory()	{
        $dir = $this->GetServerManagerRoot()."configfiles/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }
      
      public function GetSessionArchiveBaseDirectory()	{
        $dir = $this->GetServerManagerRoot()."session_archive/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }

      public function GetSessionArchivePrefix() {
        return "session_archive_";
      }

      public function GetSessionSavesBaseDirectory() {
        $dir = $this->GetServerManagerRoot()."saves/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }

      public function GetSessionSavesPrefix() {
        return "save_";
      }

      public function GetSessionLogBaseDirectory() {
        $dir = $this->GetServerManagerRoot()."log/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }

      public function GetSessionLogPrefix() {
        return "log_session_";
      }
      
      public function GetServerConfigBaseDirectory()	{
        $dir = $this->GetServerRoot()."running_session_config/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }
      
      public function GetServerRasterBaseDirectory() {
        $dir = $this->GetServerRoot()."raster/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }

      public function GetServerSessionArchiveBaseDirectory() {
        $dir = $this->GetServerRoot()."session_archive/";
        if (!is_dir($dir)) mkdir($dir, 0777);
        return $dir;
      }

      public function edit() {
        $this->SetServerName();
        $this->SetServerAddress();
        $this->SetServerDescription();

        $updateservername = Base::callAuthoriser( // doing this here because JWT won't be available elsewhere
          'updateservernamejwt.php', 
          array(
              "jwt" => $this->getJWT(),
              "audience" => $this->GetBareHost(),
              "server_id" => $this->GetServerID(),
              "server_name" => $this->GetServerName()
          ) 
        );
      }

      public function get() {
        $this->CompletePropertiesFromDB();
      }

}

?>