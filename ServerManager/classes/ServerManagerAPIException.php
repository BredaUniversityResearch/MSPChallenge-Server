<?php

use Symfony\Component\HttpKernel\Exception\HttpException;

class ServerManagerAPIException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(200, $message);
    }
}
