<?php

namespace App\Domain\Communicator;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Auth2Communicator extends AbstractCommunicator
{
    public function __construct(
        HttpClientInterface $client
    ) {
        parent::__construct($client);
        $this->setBaseURL(
            str_replace('://', '', $_ENV['AUTH_SERVER_SCHEME'] ?? 'https').'://'.
            ($_ENV['AUTH_SERVER_HOST'] ?? 'auth2.mspchallenge.info').':'.
            ($_ENV['AUTH_SERVER_PORT'] ?? 443).
            ($_ENV['AUTH_SERVER_API_BASE_PATH'] ?? '/api/')
        );
    }

    public function getResource(string $endPoint): array
    {
        $this->tokenCheck();

        return $this->call(
            'GET',
            $endPoint
        );
    }

    public function delResource(string $endPoint): void
    {
        $this->tokenCheck();

        $this->call(
            'DELETE',
            $endPoint
        );
    }

    public function postResource(string $endPoint, array $data): array
    {
        $this->tokenCheck();

        return $this->call(
            'POST',
            $endPoint,
            $data
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
}
