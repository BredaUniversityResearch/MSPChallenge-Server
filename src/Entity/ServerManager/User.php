<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $token = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $refreshTokenExpiration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pin = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $accountId = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

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

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getRefreshTokenExpiration(): ?\DateTimeInterface
    {
        return $this->refreshTokenExpiration;
    }

    public function setRefreshTokenExpiration(?\DateTimeInterface $refreshTokenExpiration): self
    {
        $this->refreshTokenExpiration = $refreshTokenExpiration;

        return $this;
    }

    public function getPin(): ?string
    {
        return $this->pin;
    }

    public function setPin(?string $pin): self
    {
        $this->pin = $pin;

        return $this;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function setAccountId(int $accountId): self
    {
        $this->accountId = $accountId;

        return $this;
    }
}
