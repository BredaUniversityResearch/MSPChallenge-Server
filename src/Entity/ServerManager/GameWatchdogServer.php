<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\GameWatchdogServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'game_watchdog_servers')]
#[ORM\Entity(repositoryClass: GameWatchdogServerRepository::class)]
#[UniqueEntity('address')]
class GameWatchdogServer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 128, unique: true)]
    private ?string $name = null;

    /**
     * with trailing slash
     */
    #[Assert\NotBlank]
    #[Assert\Url]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    private ?bool $available = true;

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

    public function getAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(?bool $available): self
    {
        $this->available = $available;

        return $this;
    }
}
