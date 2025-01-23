<?php

namespace App\Entity\ServerManager;

use App\Entity\Interface\WatchdogInterface;
use App\Entity\Trait\WatchdogTrait;
use App\Repository\ServerManager\GameWatchdogServerRepository;
use App\Validator as AcmeAssert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'game_watchdog_servers', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_server_id', columns: ['server_id']),
    new ORM\UniqueConstraint(name: 'uq_scheme_address_port', columns: ['scheme', 'address', 'port']),
])]
#[ORM\Entity(repositoryClass: GameWatchdogServerRepository::class)]
class GameWatchdogServer implements WatchdogInterface
{
    use WatchdogTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $serverId = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 128, unique: true)]
    private ?string $name = null;

    #[Assert\NotBlank]
    #[AcmeAssert\Address]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[ORM\Column(options: ['default' => 80])]
    private int $port = 80;

     #[ORM\Column(length: 255, options: ['default' => 'http'])]
    private string $scheme = 'http';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    private ?bool $available = true;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function getAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(?bool $available): self
    {
        $this->available = $available;

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = (
            $this->getServerId()->toRfc4122() == WatchdogInterface::INTERNAL_SERVER_ID_RFC4122 ?
                $_ENV['WATCHDOG_SCHEME'] : null
            ) ??
            $this->getScheme();
        $port = (
            $this->getServerId()->toRfc4122() == WatchdogInterface::INTERNAL_SERVER_ID_RFC4122 ?
                $_ENV['WATCHDOG_PORT'] : null
            ) ??
            $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
