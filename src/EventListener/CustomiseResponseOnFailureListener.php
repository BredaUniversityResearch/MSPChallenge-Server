<?php

namespace App\EventListener;

use App\Domain\Common\MessageJsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;

class CustomiseResponseOnFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $response = new MessageJsonResponse(
            status: 401,
            message: 'Bad token, please get a new one'
        );
        $event->setResponse($response);
    }
}
