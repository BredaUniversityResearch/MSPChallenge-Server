<?php

namespace App\Domain\API\v1;

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

    abstract public function getName(): string;
    abstract public function authenticate(string $username, string $password): bool;

    /** @noinspection SpellCheckingInspection */
    abstract public function checkuser(string $username): array;
}
