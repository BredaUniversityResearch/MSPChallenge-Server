<?php

class ServerManager 
{
    private static $_instance = null;
    private $_db, $_server_id, $_server_name, $_server_address, $_server_versions, $_server_current_version, $_server_root, $_server_manager_root, $_server_upgrades, $_msp_auth_url, $_msp_auth_api;

      public function __construct() {
        $this->_db = DB::getInstance();
        $this->_server_id = $this->_db->cell("settings.value", array("name", "=", "server_id"));
        $this->_server_name = $this->_db->cell("settings.value", array("name", "=", "server_name"));
        $this->_server_address = $this->_db->cell('game_servers.address', array("id", "=", 1));
        $this->_server_versions = array(
          20210301 => "4.0-beta7",
          20210328 => "4.0-beta8"
        );
        $this->_server_current_version = end($this->_server_versions);
        $this->_server_upgrades = array(
          "From40beta7To40beta8" // make sure this function exists in server API update class and is actually callable - just letters and numbers of course
        );
        $this->SetRootVars();
        $this->_msp_auth_url = "https://auth.mspchallenge.info";
        $this->_msp_auth_api = "https://auth.mspchallenge.info/usersc/plugins/apibuilder/authmsp/";
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

      public function CheckForUpgrade(int $builddate) {
        // checks using $builddate of server session whether it can be upgraded to current version
        // determine the version of server session using $builddate
        $versiondetermined = "";
        foreach ($this->_server_versions as $date => $version) {
          if ($date <= $builddate) $versiondetermined = $version;
          else break;
        }
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
        return $this->_server_id;
      }

      public function GetServerName() {
        return $this->_server_name;
      }

      public function freshinstall() {
        return (empty($this->_server_id));
      }

      public function install($user=null) {
          if ($this->freshinstall()) {
              $this->SetServerID();
              $this->SetServerName($user);
              return true;
          }
          return false;
      }
      
      private function SetServerID()  {
        if (empty($this->_server_id)) {
            $this->_server_id = uniqid('', true);
            $this->_db->query("INSERT INTO settings (name, value) VALUES (?, ?);", array("server_id", $this->_server_id));
        }
        return $this->_server_id;
      }

      private function SetServerName($user=null) {
        if (empty($this->_server_name)) {
            if (empty($user)) return false;
            //obtain a new random server_name
            $currentDateTime = date("Ymd");
            $serverName =  $user->data()->username . '_' . $currentDateTime;
            try {
                $response = file_get_contents('http://names.drycodes.com/1?nameOptions=cities');
                $data = json_decode($response);
                if ($data && (count($data) > 0)) {
                    $serverName = $user->data()->username . "_" . strtolower($data[0]) . '_' . $currentDateTime;
                }
            } catch (Exception $e) { }
            $this->_server_name = $serverName;
            $this->_db->query("INSERT INTO settings (name, value) VALUES (?, ?);", array("server_name", $this->_server_name));
        }
        return $this->_server_name;
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
        // e.g. localhost
        if (!empty($_SERVER['SERVER_NAME'])) {
          if ($_SERVER['SERVER_NAME'] != $this->_server_address) {
            return $_SERVER['SERVER_NAME'];
          }
        }  
        return $this->_server_address;
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
        return $this->GetServerManagerRoot()."configfiles/";
      }
      
      public function GetSessionArchiveBaseDirectory()	{
        return $this->GetServerManagerRoot()."session_archive/";
      }

      public function GetSessionSavesBaseDirectory() {
        return $this->GetServerManagerRoot()."saves/";
      }
      
      public function GetServerConfigBaseDirectory()	{
        return $this->GetServerRoot()."running_session_config/";
      }
      
      public function GetServerRasterBaseDirectory() {
        return $this->GetServerRoot()."raster/";
      }

}

?>