<?php

namespace App\EventListener;

use App\Domain\API\v1\Config;
use App\Exception\MSPAuth2RedirectException;
use ServerManager\MSPAuthException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class ExceptionListener
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if ($e instanceof MSPAuth2RedirectException) {
            $url = str_replace('://', '', $_ENV['AUTH_SERVER_SCHEME'] ?? 'https').'://'.
                $_ENV['AUTH_SERVER_HOST'].':'.($_ENV['AUTH_SERVER_PORT'] ?? 443).
                '/sso?redirect='.urlencode($event->getRequest()->getUri());
            $event->setResponse(new RedirectResponse($url));
            return;
        }
        if ($e instanceof MSPAuthException) {
            $urlBase = $this->urlGenerator->generate(
                'server_manager_index',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $event->setResponse(new RedirectResponse(
                Config::GetInstance()->getMSPAuthBaseURL()
                .'/sso?redirect='.
                urlencode($urlBase.'login_php')
            ));
        }
    }
}
