<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BaseController extends AbstractController
{
    protected function getSessionIdFromRequest(Request $request): int
    {
        // check query parameter session
        $sessionId = $request->attributes->get('sessionId');
        if (!$sessionId || !is_numeric($sessionId)) {
            // this should not happen, since the CheckApiSessionIdListener should have already checked this
            throw new BadRequestHttpException('Missing or invalid session ID');
        }
        return (int)$sessionId;
    }

    public static function wrapPayloadForResponse(
        bool $success,
        mixed $payload = null,
        ?string $message = null
    ): array {
        return [
            'header_type' => '',
            'header_data' => '',
            'success' => $success,
            'message' => $message,
            'payload' => $payload
        ];
    }
}
