<?php

namespace App\EventListener;

use App\Domain\Common\MessageJsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ApiResponseWrapperListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $inner = $event->getResponse();
        // only process Json responses
        $hasJsonContentType = preg_match(
            '/application\/[^;+]*\+?json/',
            $inner->headers->get('Content-Type')
        ) === 1;
        if (!(
                $hasJsonContentType ||
                ($inner instanceof JsonResponse)
            )) {
            return;
        }

        if ($inner->getStatusCode() >= 200 && $inner->getStatusCode() < 300) {
            $event->setResponse(new JsonResponse(
                [
                    'message' => ($inner instanceof MessageJsonResponse) ? $inner->getMessage() : null,
                    'success' => true,
                    'payload' => $hasJsonContentType ?
                        json_decode($inner->getContent(), true) : $inner->getContent()
                ],
                $inner->getStatusCode()
            ));
            return;
        }

        if ($inner->getStatusCode() >= 400) {
            $event->setResponse(new JsonResponse(
                [
                    'success' => false,
                    'message' => ((
                            ($inner instanceof JWTAuthenticationFailureResponse) ||
                            ($inner instanceof MessageJsonResponse)
                        ) ?
                        $inner->getMessage() : null) ?? Response::$statusTexts[$inner->getStatusCode()],
                    'payload' => $hasJsonContentType ?
                        json_decode($inner->getContent(), true) : $inner->getContent()
                ],
                $inner->getStatusCode()
            ));
        }
    }
}
