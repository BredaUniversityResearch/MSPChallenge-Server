<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Repository\ServerManager\GameServerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'game_servers')]
#[ORM\Entity(repositoryClass: GameServerRepository::class)]
class GameServer extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $address = null;

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
}
