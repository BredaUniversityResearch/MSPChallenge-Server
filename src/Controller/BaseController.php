<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

class BaseController extends AbstractController
{
    protected function getSessionIdFromRequest(Request $request): int
    {
        // check query parameter session
        $sessionId = $request->attributes->get('session');
        if (!$sessionId || !is_numeric($sessionId)) {
            // this should not happen, since the CheckApiSessionIdListener should have already checked this
            throw new BadRequestHttpException('Missing or invalid session ID');
        }
        return (int)$sessionId;
    }

    protected function getServerIdFromRequest(Request $request): Uuid
    {
        $serverId = $request->headers->get('x-server-id');
        if (!$serverId || !Uuid::isValid($serverId)) {
            throw new BadRequestHttpException('Missing or invalid header X-Server-Id. Must be a valid UUID');
        }
        return Uuid::fromString($serverId);
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
