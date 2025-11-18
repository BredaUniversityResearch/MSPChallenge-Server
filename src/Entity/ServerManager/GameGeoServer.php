<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Entity\Mapping\Property\SecretsChoiceType;
use App\Repository\ServerManager\GameGeoServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Validator\Constraints as Assert;

#[AppMappings\Plurals('GeoServer', 'GeoServers')]
#[AppMappings\ReadonlyIDs([1])]
#[ORM\Table(name: 'game_geoservers')]
#[ORM\Entity(repositoryClass: GameGeoServerRepository::class)]
#[UniqueEntity('address')]
class GameGeoServer extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[AppMappings\Property\TableColumn(label: "Name")]
    #[Assert\NotBlank]
    #[ORM\Column(length: 128)]
    private ?string $name = null;

    #[AppMappings\Property\TableColumn(label: "Fully-qualified URL")]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[AppMappings\Property\FormFieldType(type: SecretsChoiceType::class)]
    #[Assert\NotBlank]
    #[ORM\Column(name: 'username', length: 255)]
    private ?string $usernameSecret = null;

    #[AppMappings\Property\FormFieldType(type: SecretsChoiceType::class)]
    #[Assert\NotBlank]
    #[ORM\Column(name: 'password', length: 255)]
    private ?string $passwordSecret = null;

    #[AppMappings\Property\TableColumn(action: true, toggleable: true, availability: true)]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    private ?bool $available = true;

    private ?AbstractVault $vault;

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
        if ($this->vault === null) {
            return null;
        }
        return $this->vault->reveal($this->usernameSecret);
    }

    public function getUsernameSecret(): ?string
    {
        return $this->usernameSecret;
    }

    public function setUsernameSecret(?string $usernameSecret): self
    {
        $this->usernameSecret = $usernameSecret;

        return $this;
    }

    public function getPassword(): ?string
    {
        if ($this->vault === null) {
            return null;
        }
        return $this->vault->reveal($this->passwordSecret);
    }

    public function getPasswordSecret(): ?string
    {
        return $this->passwordSecret;
    }

    public function setPasswordSecret(?string $passwordSecret): self
    {
        $this->passwordSecret = $passwordSecret;

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

    public function getVault(): ?AbstractVault
    {
        return $this->vault;
    }

    public function setVault(?AbstractVault $vault): void
    {
        $this->vault = $vault;
    }
}
