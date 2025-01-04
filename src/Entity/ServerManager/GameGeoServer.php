<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\GameGeoServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'game_geoservers')]
#[ORM\Entity(repositoryClass: GameGeoServerRepository::class)]
#[UniqueEntity('address')]
class GameGeoServer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 128)]
    private ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Url]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $username = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private ?bool $available;

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

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

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
