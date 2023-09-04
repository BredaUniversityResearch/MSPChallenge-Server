<?php

namespace App\EventListener;

use App\Controller\SessionAPI\BaseController;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomiseResponseOnFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $response = new JsonResponse(
            BaseController::wrapPayloadForResponse([], 'Bad token, please get a new one'),
            401
        );
        $event->setResponse($response);
    }
}
