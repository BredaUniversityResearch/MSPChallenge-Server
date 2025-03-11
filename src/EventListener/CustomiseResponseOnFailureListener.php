<?php

namespace App\EventListener;

use App\Controller\BaseController;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomiseResponseOnFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $response = new JsonResponse(
            BaseController::wrapPayloadForResponse(false, message: 'Bad token, please get a new one'),
            401
        );
        $event->setResponse($response);
    }
}
