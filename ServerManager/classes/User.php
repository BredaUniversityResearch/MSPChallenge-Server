<?php
/*
UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class User {
	private $_db, $_data, $_sessionName, $_isLoggedIn, $_cookieName,$_isNewAccount;
	public $tableName = 'users';



	public function __construct($user = null){
		$this->_db = DB::getInstance();
		$this->_sessionName = Config::get('session/session_name');
		$this->_cookieName = Config::get('remember/cookie_name');


		if (!$user) {
			if (Session::exists($this->_sessionName)) {
				$user = Session::get($this->_sessionName);

				if ($this->find($user)) {
					$this->_isLoggedIn = true;
				} else {
					//process Logout
				}
			}
		} else {
			$this->find($user);
		}
	}

	public function isAuthorised(){
		if ($this->exists()) {
			$servermanager = ServerManager::getInstance();
			$params = array("jwt" => Session::get("currentToken"), "server_id" => $servermanager->GetServerID(), "audience" => $servermanager->GetBareHost());
			$api_url = $servermanager->GetMSPAuthAPI().'authjwt.php';
			$authorize = json_decode(CallAPI("POST", $api_url, $params));
			if (isset($authorize->success)) {
				if ($authorize->success) {
						return true;
				}
				else {
					if (isset($authorize->error)) {
						if ($authorize->error == 503) {
							die('MSP Challenge Authoriser cannot be reached. Are you sure you are connected to the internet?');
						}
					}
				}
			}
		}
		return false;
	}

	public function find($user = null,$loginHandler = null){

		if ($user) {
				if($loginHandler!==null) {
					if(!filter_var($user, FILTER_VALIDATE_EMAIL) === false){
						$field = 'email';
					}else{
						$field = 'username';
					}
				}
				else {
				if(is_numeric($user)){
					$field = 'id';
				}elseif(!filter_var($user, FILTER_VALIDATE_EMAIL) === false){
					$field = 'email';
				}else{
					$field = 'username';
				}
			}
			$data = $this->_db->get('users', array($field, '=', $user));

			if ($data->count()) {
				$this->_data = $data->first();
				if($this->data()->account_id == 0 && $this->data()->account_owner == 1){
					$this->_data->account_id = $this->_data->id;
				}
				return true;
			}
		}
		return false;
	}

	public function exists(){
		return (!empty($this->_data)) ? true : false;
	}

	public function data(){
		return $this->_data;
	}

	public function isLoggedIn(){
		return $this->_isLoggedIn;
	}

	public function notLoggedInRedirect($location){
		if ($this->_isLoggedIn){
			return true;
		}
		else {
			Redirect::to($location);
		}
	}

	public function hastobeLoggedIn() {
		if (!$this->_isLoggedIn) {
			$this->forbidden();
		}
	}

	public function forbidden() {
		http_response_code(404);
		die();
	}

	public function logout(){
		session_unset();
		session_destroy();
	}


}
