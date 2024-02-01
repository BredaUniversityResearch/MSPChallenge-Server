<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Fishing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $fishingId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'fishing')]
    #[ORM\JoinColumn(name: 'fishing_country_id', referencedColumnName: 'country_id')]
    private ?Country $country;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'fishing')]
    #[ORM\JoinColumn(name: 'fishing_plan_id', referencedColumnName: 'plan_id')]
    private ?Plan $plan;

    #[ORM\Column(type: Types::STRING, length: 75, nullable: true)]
    private ?string $fishingType;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $fishingAmount;

    #[ORM\Column(type: Types::SMALLINT, length: 1, nullable: true, options: ['default' => 0])]
    private ?int $fishingActive;

    public function getFishingId(): ?int
    {
        return $this->fishingId;
    }

    public function setFishingId(?int $fishingId): Fishing
    {
        $this->fishingId = $fishingId;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): Fishing
    {
        $this->country = $country;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): Fishing
    {
        $this->plan = $plan;
        return $this;
    }

    public function getFishingType(): ?string
    {
        return $this->fishingType;
    }

    public function setFishingType(?string $fishingType): Fishing
    {
        $this->fishingType = $fishingType;
        return $this;
    }

    public function getFishingAmount(): ?float
    {
        return $this->fishingAmount;
    }

    public function setFishingAmount(?float $fishingAmount): Fishing
    {
        $this->fishingAmount = $fishingAmount;
        return $this;
    }

    public function getFishingActive(): ?int
    {
        return $this->fishingActive;
    }

    public function setFishingActive(?int $fishingActive): Fishing
    {
        $this->fishingActive = $fishingActive;
        return $this;
    }


}
