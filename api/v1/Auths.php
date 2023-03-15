<?php

namespace App\Domain\API\v1;

abstract class Auths extends Base
{
    abstract public function getName(): string;
    abstract public function authenticate(string $username, string $password): bool;

    /** @noinspection SpellCheckingInspection */
    abstract public function checkUser(string $username): array;
}
