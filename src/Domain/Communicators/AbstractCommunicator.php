<?php

namespace App\Domain\Communicators;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractCommunicator
{
    protected HttpClientInterface $client;

    protected ?string $basePath = null;
    protected ?string $username = null;
    protected ?string $password = null;
    protected ?string $token = null;

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

        $response = $this->client->request(
            $method,
            $this->getBasePath().$endPoint,
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
    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * @param string|null $basePath
     */
    public function setBasePath(?string $basePath): self
    {
        $this->basePath = $basePath;

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
}
