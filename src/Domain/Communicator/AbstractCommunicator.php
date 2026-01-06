<?php

namespace App\Domain\Communicator;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractCommunicator
{
    protected HttpClientInterface $client;

    protected ?string $baseURL = null;
    protected ?string $username = null;
    protected ?string $password = null;
    protected ?string $token = null;
    protected ?string $lastCompleteURLCalled = null;

    public function __construct(
        HttpClientInterface $client
    ) {
        $this->client = $client;
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function call(
        string $method,
        string $endPoint,
        array $data = [],
        array $headers = [],
        bool $asArray = true
    ): string|array|null {
        if (!empty($this->getToken())) {
            $options['auth_bearer'] = $this->getToken();
        } elseif (!empty($this->getUsername() && !empty($this->getPassword()))) {
            $options['auth_basic'] = $this->getUsername().':'.$this->getPassword();
        }
        $options['json'] = $data;
        $options['headers'] = $headers;

        $this->lastCompleteURLCalled = $this->getBaseURL().$endPoint;

        $response = $this->client->request(
            $method,
            $this->lastCompleteURLCalled,
            $options
        );
        if ($method == 'DELETE') {
            return null;
        }
        if ($asArray) {
            return $response->toArray();
        }
        return $response->getContent();
    }

    /**
     * @return string|null
     */
    public function getBaseURL(): ?string
    {
        return $this->baseURL;
    }

    /**
     * @param string|null $baseURL
     * @return static
     */
    public function setBaseURL(?string $baseURL): static
    {
        $this->baseURL = $baseURL;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return static
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return static
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getLastCompleteURLCalled(): ?string
    {
        return $this->lastCompleteURLCalled;
    }
}
