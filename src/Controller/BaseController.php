<?php

namespace App\Controller;

use App\Domain\Helper\RequestDataExtractor;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        return RequestDataExtractor::getSessionIdFromRequest($request);
    }

    protected function getServerIdFromRequest(Request $request): Uuid
    {
        return RequestDataExtractor::getServerIdFromRequest($request);
    }
}
