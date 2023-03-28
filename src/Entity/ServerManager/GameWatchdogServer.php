<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\GameWatchdogServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'game_watchdog_servers')]
#[ORM\Entity(repositoryClass: GameWatchdogServerRepository::class)]
class GameWatchdogServer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private ?string $name = null;

    /**
     * with trailing slash
     */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $available = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function setAvailable(int $available): self
    {
        $this->available = $available;

        return $this;
    }
}
