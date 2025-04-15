<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\ImmersiveSessionDockerApiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImmersiveSessionDockerApiRepository::class)]
class ImmersiveSessionDockerApi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column]
    private ?int $port = null;

    #[ORM\Column(length: 255)]
    private ?string $scheme = null;

    #[ORM\Column]
    private ?bool $available = null;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastPing = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): static
    {
        $this->available = $available;

        return $this;
    }

    public function getLastPing(): ?\DateTimeInterface
    {
        return $this->lastPing;
    }

    public function setLastPing(?\DateTimeInterface $lastPing): static
    {
        $this->lastPing = $lastPing;

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = str_replace('://', '', $this->getScheme());
        $port = $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
