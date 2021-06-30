<?php

/* 
when you create your own authentication provider subclass, make sure you define these methods:
public function getName();
public function authenticate($username, $password);
just like in the default MSP Challenge authentication provider below.
*/

class Auth_MSP extends Auths
{

    private $name = 'MSP Challenge';
    
    public function getName() 
    {
        return $this->name; 
    }   
    
    public function authenticate($username, $password)
    {
        // get a temp JWT from the Authoriser for further communication
        $audience = GameSession::GetRequestApiRoot();
        try {
            $jwt_return = json_decode($this->CallBack(Config::getInstance()->GetAuthJWTRetrieval(), 
                                    array("audience" => $audience), 
                                    array(), // no headers
                                    false, // synchronous, so wait
                                    true)); // post as json
        } catch (Exception $e) {
            $jwt_return = new stdClass();
            $jwt_return->success = false;
        }
        if ($jwt_return->success) {
            $jwt = $jwt_return->jwt;
            // use the jwt to check the sent username and password at the Authoriser
            $usercheck_return = json_decode($this->CallBack(Config::getInstance()->GetAuthJWTUserCheck(), 
                                    array("jwt" => $jwt, 
                                          "audience" => $audience, 
                                          "username" => $username, 
                                          "password" => $password), 
                                    array(), // no headers 
                                    false,  // synchronous, so wait
                                    true)); // post as json
            if ($usercheck_return->success) {
                return $usercheck_return->username; //$usercheck_return->email;
            }
            else {
                throw new Exception("Username and/or password incorrect.");
            }
        }
        throw new Exception("Could not authenticate through ".$this->getName().". Try again later or get in touch with your facilitator.");
    }

    public function checkuser($users)
    {
        // get a temp JWT from the Authoriser for further communication
        $audience = GameSession::GetRequestApiRoot();
        try {
            $jwt_return = json_decode($this->CallBack(Config::getInstance()->GetAuthJWTRetrieval(), 
                                    array("audience" => $audience), 
                                    array(), // no headers
                                    false, // synchronous, so wait
                                    true)); // post as json
        } catch (Exception $e) {
            $jwt_return = new stdClass();
            $jwt_return->success = false;
        }
        if ($jwt_return->success) {
            $jwt = $jwt_return->jwt;
            // use the jwt to check the sent username and password at the Authoriser
            $usercheck_return = json_decode($this->CallBack(Config::getInstance()->GetAuthJWTUserCheck(), 
                                    array("jwt" => $jwt, 
                                          "audience" => $audience, 
                                          "username" => $users), 
                                    array(), // no headers 
                                    false,  // synchronous, so wait
                                    true)); // post as json
            if ($usercheck_return->success) {
                return array("found" => $usercheck_return->found, "notfound" => $usercheck_return->notfound);
            }
            else {
                throw new Exception("Users not found.");
            }
        }
        throw new Exception("Could not authenticate through ".$this->getName().". Try again later or get in touch with your facilitator.");
    }

}

?>