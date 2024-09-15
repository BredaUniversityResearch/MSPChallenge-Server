<?php
namespace App\Twig;

use App\VersionsProvider;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class VersionsReturner extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly VersionsProvider $versionsProvider
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'version' => $this->versionsProvider->getVersion(),
            'address' => $_ENV['URL_WEB_SERVER_HOST'].':'.$_ENV['URL_WEB_SERVER_PORT'] ?? $_ENV['WEB_SERVER_PORT'] ?? 80
        ];
    }
}
