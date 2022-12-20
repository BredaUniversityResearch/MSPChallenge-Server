<?php

namespace ServerManager;

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

use App\Domain\Helper\Config;
use Exception;

class User extends Base
{
    private ?DB $db = null;
    private $data;
    private $sessionName;
    private bool $isLoggedIn = false;
    public string $tableName = 'users';

    public function __construct($user = null)
    {
        $this->db = DB::getInstance();
        $this->sessionName = Config::get('session/session_name');
        if (!$user) {
            if (Session::exists($this->sessionName)) {
                $user = Session::get($this->sessionName);

                if ($this->find($user)) {
                    $this->isLoggedIn = true;
                }
            }
        } else {
            $this->find($user);
        }
    }

    public function isAuthorised()
    {
        if ($this->exists()) {
            $servermanager = ServerManager::getInstance();
            $params = array(
                "jwt" => Session::get("currentToken"),
                "server_id" => $servermanager->GetServerID(),
                "audience" => $servermanager->GetBareHost()
            );
            $authorize = Base::callAuthoriser("authjwt.php", $params);
            if (isset($authorize["success"])) {
                if ($authorize["success"]) {
                    return true;
                } else {
                    if (isset($authorize["error"])) {
                        if ($authorize["error"] == 503) {
                            die(
                                'MSP Challenge Authoriser cannot be reached. ' .
                                'Are you sure you are connected to the internet?'
                            );
                        }
                    }
                }
            }
        }
        return false;
    }

    public function find($user = null, $loginHandler = null): bool
    {
        if ($user) {
            $field = $loginHandler!==null ? 'username' : (
                is_numeric($user) ? 'id' : 'username'
            );
            $data = $this->db->get('users', array($field, '=', $user));
            if ($data->count()) {
                $this->data = $data->first();
                if ($this->data()->account_id == 0 && $this->data()->account_owner == 1) {
                    $this->data->account_id = $this->data->id;
                }
                return true;
            }
        }
        return false;
    }

    public function exists(): bool
    {
        return !empty($this->data);
    }

    public function data()
    {
        return $this->data;
    }

    public function isLoggedIn(): bool
    {
        return $this->isLoggedIn;
    }

    public function hasToBeLoggedIn()
    {
        if ($this->isLoggedIn) {
            return;
        }
        if (isset($_POST['session_id']) && isset($_POST['token'])) {
            try {
                $gamesession = new GameSession;
                $gamesession->id = $_POST['session_id'];
                $gamesession->get();
                if ($gamesession->api_access_token == $_POST['token']) {
                    return;
                }
                $server_call = self::callServer(
                    "Security/checkaccess",
                    array(
                        "scope" => "ServerManager",
                    ),
                    $_POST['session_id'],
                    $_POST['token']
                );
                if ($server_call["success"] && $server_call["payload"]["status"] == "UpForRenewal") {
                    return;
                }
            } catch (Exception $e) {
                $this->forbidden();
            }
        }
        $this->forbidden();
    }

    public function forbidden(): never
    {
        http_response_code(404);
        die();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
    }
}
