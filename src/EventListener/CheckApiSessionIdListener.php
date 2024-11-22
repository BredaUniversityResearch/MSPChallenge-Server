<?php

namespace App\EventListener;

use App\Domain\API\v1\Router;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckApiSessionIdListener
{
    private array $pathPatterns;

    public function __construct(array $areas)
    {
        $this->pathPatterns = $areas['path_patterns'] ?? [];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach ($this->pathPatterns as $pattern) {
            if (!preg_match('#' . $pattern . '#', $path)) {
                continue;
            }
            $sessionId = $request->headers->get('X-Session-ID');
            if (!$sessionId || !is_numeric($sessionId)) {
                $event->setResponse(new JsonResponse(
                    Router::formatResponse(
                        false,
                        'Missing or invalid X-Session-ID header',
                        null,
                        __CLASS__,
                        __FUNCTION__
                    ),
                    Response::HTTP_BAD_REQUEST
                ));
            }
            return;
        }
    }
}
