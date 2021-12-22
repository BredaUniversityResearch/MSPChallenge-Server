<?php

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
abstract class Auths extends Base
{
    // delete this after initial development!
    private const ALLOWED = array(
        ["getName", Security::ACCESS_LEVEL_FLAG_NONE],
        ["authenticate", Security::ACCESS_LEVEL_FLAG_NONE],
        ["checkuser", Security::ACCESS_LEVEL_FLAG_NONE],
        ["authorize", Security::ACCESS_LEVEL_FLAG_NONE],
    );
    // because authconn will talk to the auth providers only

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    abstract public function getName();
    abstract public function authenticate(string $username, string $password);

    /** @noinspection SpellCheckingInspection */
    abstract public function checkuser(string $username): array;

    public function authorize(string $username, string $team)
    {
    }
}
