<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $countryId;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $countryName;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $countryColour;

    #[ORM\Column(type: Types::INTEGER, length: 1, nullable: true, options: ['default' => 0])]
    private ?int $countryIsManager;

    public function getCountryId(): ?int
    {
        return $this->countryId;
    }

    public function setCountryId(?int $countryId): Country
    {
        $this->countryId = $countryId;
        return $this;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function setCountryName(?string $countryName): Country
    {
        $this->countryName = $countryName;
        return $this;
    }

    public function getCountryColour(): ?string
    {
        return $this->countryColour;
    }

    public function setCountryColour(?string $countryColour): Country
    {
        $this->countryColour = $countryColour;
        return $this;
    }

    public function getCountryIsManager(): ?int
    {
        return $this->countryIsManager;
    }

    public function setCountryIsManager(?int $countryIsManager): Country
    {
        $this->countryIsManager = $countryIsManager;
        return $this;
    }
}
