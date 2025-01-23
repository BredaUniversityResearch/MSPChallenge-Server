<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class MSPAuth2RedirectException extends HttpException
{
    public function __construct()
    {
        parent::__construct(200, '');
    }
}
