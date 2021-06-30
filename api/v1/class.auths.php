<?php

abstract class Auths extends Base
{
    // delete this after initial development!
    protected $allowed = array(
        ["getName", Security::ACCESS_LEVEL_FLAG_NONE],
        ["authenticate", Security::ACCESS_LEVEL_FLAG_NONE],
        ["checkuser", Security::ACCESS_LEVEL_FLAG_NONE],
        ["authorize", Security::ACCESS_LEVEL_FLAG_NONE],
    );
    // because authconn will talk to the auth providers only

    public function __construct($method = "")
    {
        parent::__construct($method);
    }

    abstract public function getName();
    abstract public function authenticate($username, $password);
    abstract public function checkuser($username);

    public function authorize($username, $team) 
    {

    }

}

?>