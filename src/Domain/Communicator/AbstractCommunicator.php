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

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function call($method, $endPoint, $data = [], $headers = [], $asArray = true): string|array
    {
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
     */
    public function setBaseURL(?string $baseURL): self
    {
        $this->baseURL = $baseURL;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getLastCompleteURLCalled(): ?string
    {
        return $this->lastCompleteURLCalled;
    }
}
