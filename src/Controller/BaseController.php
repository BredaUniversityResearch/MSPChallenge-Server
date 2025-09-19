<?php

namespace App\Controller;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

class BaseController extends AbstractController
{
    public function __construct(
        protected readonly string $projectDir,
        protected readonly ConnectionManager $connectionManager,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ) {
    }

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
}
