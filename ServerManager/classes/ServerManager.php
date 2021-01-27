<?php

class ServerManager {
    private static $_instance = null;
    private $_db, $_server_id, $_server_name, $_server_address;

      public function __construct() {
        $this->_db = DB::getInstance();
        $this->_server_id = $this->_db->cell("settings.value", array("name", "=", "server_id"));
        $this->_server_name = $this->_db->cell("settings.value", array("name", "=", "server_name"));
        $this->_server_address = $this->_db->cell('game_servers.address', array("id", "=", 1));
      }

      public static function getInstance() {
		if (!isset(self::$_instance)) {
			self::$_instance = new ServerManager();
		}
		return self::$_instance;
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
      
      public function GetServerURL() {
        return $this->_server_address;
      }

      public function GetServerURLBySessionId($sessionId) {
        $url = Config::get('msp_server_protocol').$this->GetTranslatedServerURL().Config::get('code_branch')."/";
        $url .= $sessionId;
        return $url;
      }
      
      public function GetFullSelfAddress() {
        return $this->GetBareHost().Config::get('code_branch')."/ServerManager/";
      }
      
      public function GetBareHost() {
        return Config::get('msp_servermanager_protocol').$this->GetTranslatedServerURL();
      }
      
      public function GetTranslatedServerURL() {
        if (!empty($_SERVER['SERVER_NAME'])) {
          if ($_SERVER['SERVER_NAME'] != $this->_server_address) {
            return $_SERVER['SERVER_NAME'];
          }
        }  
        return $this->_server_address;
      }

}

?>