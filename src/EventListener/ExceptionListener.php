<?php

namespace App\EventListener;

use ServerManager\API;
use ServerManager\ServerManagerAPIException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        $e = $event->getThrowable();
        if ($e instanceof ServerManagerAPIException) {
            $api = new API;
            $api->setMessage($e->getMessage());
            $payload = $api->prepareReturn();
            $event->setResponse(new JsonResponse($payload));
        }
    }
}
