<?php

namespace App\Controller\ServerManager;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/{manager}/logout',
    name: 'manager_logout',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class LogoutController extends AbstractController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Clears everything: the Symfony security token, the cached JWT ('token'),
        // terms.target_path, all of it — and rotates the session ID.
        $request->getSession()->invalidate();

        $scheme = str_replace('://', '', $_ENV['AUTH_SERVER_SCHEME'] ?? 'https');
        $host = $_ENV['AUTH_SERVER_HOST'] ?? 'auth2.mspchallenge.info';
        $port = $_ENV['AUTH_SERVER_PORT'] ?? 443;

        return new RedirectResponse("{$scheme}://{$host}:{$port}/logout");
    }
}
