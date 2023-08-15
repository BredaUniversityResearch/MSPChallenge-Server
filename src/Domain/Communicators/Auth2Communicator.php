<?php

namespace App\Domain\Communicators;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Auth2Communicator extends AbstractCommunicator
{

    public function __construct(
        HttpClientInterface $client
    ) {
        $this->client = $client;
        $this->setBasePath(
            ($_ENV['AUTH_SERVER_SCHEME'] ?? 'https') . '://' .
            ($_ENV['AUTH_SERVER_HOST'] ?? 'auth2.mspchallenge.info') . ':' .
            ($_ENV['AUTH_SERVER_PORT'] ?? 443).
            ($_ENV['AUTH_SERVER_API_BASE_PATH'] ?? '/api/')
        );
    }

    public function getResource($endPoint): array
    {
        $this->tokenCheck();

        return $this->call(
            'GET',
            $endPoint
        );
    }

    private function tokenCheck(): void
    {
        if (is_null($this->getToken()) && !is_null($this->getUsername()) && !is_null($this->getPassword())) {
            $this->setToken($this->call(
                'POST',
                'login_check',
                ['username' => $this->username, 'password' => $this->password]
            )["token"] ?? '');
        }
    }

    public function postResource($endPoint, $data): array
    {
        $this->tokenCheck();

        return $this->call(
            'POST',
            $endPoint,
            $data
        );
    }
}
