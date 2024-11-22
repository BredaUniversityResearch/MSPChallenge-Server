<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BaseController extends AbstractController
{
    protected function getSessionIdFromHeaders(HeaderBag $headers): string
    {
        $sessionId = $headers->get('X-Session-ID');
        if (!$sessionId || !is_numeric($sessionId)) {
            // this should not happen, since the CheckApiSessionIdListener should have already checked this
            throw new BadRequestHttpException('Missing or invalid X-Session-ID header');
        }
        return $sessionId;
    }

    public static function wrapPayloadForResponse(array $payload, ?string $message = null): array
    {
        return [
            'header_type' => '',
            'header_data' => '',
            'success' => is_null($message),
            'message' => $message,
            'payload' => $payload
        ];
    }
}
