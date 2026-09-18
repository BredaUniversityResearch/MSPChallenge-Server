<?php

namespace App\EventListener;

use App\Domain\API\v1\Config;
use App\Exception\MSPAuth2RedirectException;
use ServerManager\MSPAuthException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
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

            // When present, instead of a raw redirect — which Turbo would try and fail to slot into the frame —
            //   we return a tiny HTML page whose only job is window.top.location.href = ...,
            //   which breaks out of the frame and does a real full-page navigation of the whole tab.
            //   That lands the user on auth2's actual SSO/login page as a normal page load, not a broken fragment.
            if ($event->getRequest()->headers->has('Turbo-Frame')) {
                $event->setResponse(new Response(
                    sprintf('<script>window.top.location.href = %s;</script>', json_encode($url)),
                    200,
                    ['Content-Type' => 'text/html']
                ));
                return;
            }

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
