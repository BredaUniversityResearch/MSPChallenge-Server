<?php

namespace App\Controller\SessionAPI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BaseController extends AbstractController
{

    public static function success(array $payload = []): JsonResponse
    {
        return new JsonResponse(self::wrapPayloadForResponse($payload));
    }
    public static function error(string $message, $code = 500): JsonResponse
    {
        return new JsonResponse(self::wrapPayloadForResponse([], $message), $code);
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
