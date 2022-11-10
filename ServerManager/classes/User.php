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

use App\Domain\Helper\Config;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Symfony\Component\HttpFoundation\Request;

class User extends Base
{
    private $_db, $_data, $_sessionName, $_isLoggedIn, $_cookieName;
    public $tableName = 'users';

    public function __construct($user = null)
    {
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

    public function importTokenFields(array $tokenFields): ?int
    {
        // @note(MH)
        // vendor/lexik/jwt-authentication-bundle/Encoder/LcobucciJWTEncoder.php says:
        //   "Json Web Token encoder/decoder based on the lcobucci/jwt library."
        // so instead of using lexik's, we directly use the smaller lcobucci/jwt library
        // see: https://lcobucci-jwt.readthedocs.io/en/latest/parsing-tokens/
        $parser = new Parser(new JoseEncoder());
        try {
            $unencryptedToken = $parser->parse($tokenFields['token']);
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
            return null;
        }
        $username = $unencryptedToken->claims()->get('username');
        $fields = array_merge([
            'username' => $username,
            // for backwards compatibility
            'account_owner' => 0, // what is this? Used by User::find()
        ], $tokenFields);
        if (array_key_exists('refresh_token_expiration', $fields)) {
            // unix timestamp to datetime conversion
            $fields['refresh_token_expiration'] = date('Y-m-d H:i:s', $fields['refresh_token_expiration']);
        }
        $data = $this->_db->get('users', array('username', '=', $username));
        if ($data->count()) {
            $this->_db->update('users', $data->first()->id, $fields);
            return $data->first()->id;
        }
        $this->_db->insert('users', $fields);
        return $this->_db->lastId();
    }

    public function isAuthorised()
    {
        if (!$this->exists()) {
            return false;
        }

        try {
            // todo : how to handle server id??
//            $servermanager = ServerManager::getInstance();
//            $response = collect(
//                collect(
//                    Base::getCallAuthoriser('server_users')
//                )->only('hydra:member')->first()
//            )->filter(
//                fn($e) =>
//                    $e['server']['serverId'] === $servermanager->GetServerID() &&
//                    $e['user']['username'] === $this->_data->username
//                // todo: audience? = $servermanager->GetBareHost()
//            )->all();
//            return !empty($response);

            // not finding the user will trigger a HydraErrorException
            Base::getCallAuthoriser(sprintf('users/%s', $this->_data->username));
        } catch (Exception $e) {
            return false;
        }

        // todo: we could update user table with email, isVerified, firstName, etc from response data, is it necessary ?
        return true;
    }

    public function find($user = null, $loginHandler = null)
    {

        if ($user) {
            if ($loginHandler!==null) {
                if (!filter_var($user, FILTER_VALIDATE_EMAIL) === false) {
                    $field = 'email';
                } else {
                    $field = 'username';
                }
            } else {
                if (is_numeric($user)) {
                    $field = 'id';
                } elseif (!filter_var($user, FILTER_VALIDATE_EMAIL) === false) {
                    $field = 'email';
                } else {
                    $field = 'username';
                }
            }
            $data = $this->_db->get('users', array($field, '=', $user));

            if ($data->count()) {
                $this->_data = $data->first();
                if ($this->data()->account_id == 0 && $this->data()->account_owner == 1) {
                    $this->_data->account_id = $this->_data->id;
                }
                return true;
            }
        }
        return false;
    }

    public function exists()
    {
        return !empty($this->_data);
    }

    public function data()
    {
        return $this->_data;
    }

    public function isLoggedIn()
    {
        return $this->_isLoggedIn;
    }

    public function notLoggedInRedirect($location)
    {
        if ($this->_isLoggedIn) {
            return true;
        } else {
            Redirect::to($location);
        }
    }

    public function hastobeLoggedIn()
    {
        if ($this->_isLoggedIn) {
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

    public function forbidden()
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
