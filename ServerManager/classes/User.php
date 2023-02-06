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
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\Setting;
use Exception;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\UnencryptedToken;

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

    public function isAuthorised(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        try {
            $em = SymfonyToLegacyHelper::getInstance()->getEntityManager();
            $serverUUID = $em->getRepository(Setting::class)->findOneBy(['name' => 'server_uuid']);
            if (empty($serverUUID)) {
                return false;
            }
            $response = collect(
                collect(Base::getCallAuthoriser(
                    sprintf(
                        'servers/%s/server_users',
                        $serverUUID->getValue()
                    )
                ))->pull('hydra:member')
            )->filter(function ($value) {
                return $value['user']['username'] === $this->data()->username;
            });
            return !$response->isEmpty();
        } catch (Exception $e) {
            return false;
        }
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

    public function importTokenFields(array $tokenFields): ?int
    {
        $em = SymfonyToLegacyHelper::getInstance()->getEntityManager();
        // @note(MH)
        // vendor/lexik/jwt-authentication-bundle/Encoder/LcobucciJWTEncoder.php says:
        //   "Json Web Token encoder/decoder based on the lcobucci/jwt library."
        // so instead of using lexik's, we directly use the smaller lcobucci/jwt library
        // see: https://lcobucci-jwt.readthedocs.io/en/latest/parsing-tokens/
        $parser = new Parser(new JoseEncoder());
        try {
            /** @var UnencryptedToken $unencryptedToken */
            $unencryptedToken = $parser->parse($tokenFields['token']);
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
            return null;
        }
        $username = $unencryptedToken->claims()->get('username');
        $userID = $unencryptedToken->claims()->get('id');

        $fields = array_merge([
            'username' => $username,
            'account_id' => $userID
        ], $tokenFields);
        if ($this->find($username, 'username')) {
            $this->db->update('users', $this->data()->id, $fields);
            return $this->data()->id;
        }
        $this->db->insert('users', $fields);
        $userId = $this->db->lastId();
        if ($this->find($userId)) {
            return $userId;
        }
        return null;
    }

    public function importRefreshToken(): void
    {
        if (!$this->exists()) {
            return;
        }
        $response = self::getCallAuthoriser('refresh_tokens?page=1');
        if (false === $refreshTokenData = current($response['hydra:member'] ?? [])) {
            return;
        }
        // remove empty values
        $refreshTokenData = array_filter($refreshTokenData);
        if (null === $refreshToken = ($refreshTokenData['refreshToken'] ?? null)) {
            return;
        }
        $refreshTokenExpiration = $refreshTokenData['valid'] ?? null;
        $this->db->update('users', $this->data()->id, [
            'refresh_token' => $refreshToken,
            'refresh_token_expiration' => $refreshTokenExpiration
        ]);
    }
}
