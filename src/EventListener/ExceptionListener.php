<?php

namespace App\EventListener;

use App\Exception\MSPAuth2RedirectException;
use ServerManager\API;
use ServerManager\MSPAuthException;
use ServerManager\ServerManager;
use ServerManager\ServerManagerAPIException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
            return;
        }
        if ($e instanceof MSPAuth2RedirectException) {
            $url = $_ENV['AUTH_SERVER_SCHEME'].'://'.$_ENV['AUTH_SERVER_HOST'];
            $url .= '/sso?redirect='.urlencode($event->getRequest()->getUri());
            $event->setResponse(new RedirectResponse($url));
            return;
        }
        if ($e instanceof MSPAuthException) {
            $servermanager = ServerManager::getInstance();
            $event->setResponse(new RedirectResponse(
                $servermanager->GetMSPAuthBaseURL().'/sso?redirect='.
                urlencode($servermanager->getAbsoluteUrlBase().'login.php')
            ));
        }
    }
}
