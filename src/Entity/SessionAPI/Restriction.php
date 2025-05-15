<?php

namespace App\Entity\SessionAPI;

use App\Domain\Common\EntityEnums\RestrictionSort;
use App\Domain\Common\EntityEnums\RestrictionType;
use App\src\Repository\SessionAPI\RestrictionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;

#[ORM\Entity(repositoryClass: RestrictionRepository::class)]
class Restriction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $restrictionId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'restrictionStart')]
    #[JoinColumn(name: 'restriction_start_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $restrictionStartLayer;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'restrictionEnd')]
    #[JoinColumn(name: 'restriction_end_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $restrictionEndLayer;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $restrictionStartLayerType;

    #[ORM\Column(length: 255, enumType: RestrictionSort::class)]
    private RestrictionSort $restrictionSort = RestrictionSort::INCLUSION;

    #[ORM\Column(length: 255, enumType: RestrictionType::class)]
    private RestrictionType $restrictionType;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $restrictionMessage;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $restrictionEndLayerType;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $restrictionValue = 0;

    public function getRestrictionId(): ?int
    {
        return $this->restrictionId;
    }

    public function setRestrictionId(?int $restrictionId): Restriction
    {
        $this->restrictionId = $restrictionId;
        return $this;
    }

    public function getRestrictionStartLayer(): ?Layer
    {
        return $this->restrictionStartLayer;
    }

    public function setRestrictionStartLayer(?Layer $restrictionStartLayer): Restriction
    {
        $this->restrictionStartLayer = $restrictionStartLayer;
        return $this;
    }

    public function getRestrictionEndLayer(): ?Layer
    {
        return $this->restrictionEndLayer;
    }

    public function setRestrictionEndLayer(?Layer $restrictionEndLayer): Restriction
    {
        $this->restrictionEndLayer = $restrictionEndLayer;
        return $this;
    }

    public function getRestrictionStartLayerType(): ?string
    {
        return $this->restrictionStartLayerType;
    }

    public function setRestrictionStartLayerType(?string $restrictionStartLayerType): Restriction
    {
        $this->restrictionStartLayerType = $restrictionStartLayerType;
        return $this;
    }

    public function getRestrictionSort(): RestrictionSort
    {
        return $this->restrictionSort;
    }

    public function setRestrictionSort(RestrictionSort $restrictionSort): Restriction
    {
        $this->restrictionSort = $restrictionSort;
        return $this;
    }

    public function getRestrictionType(): RestrictionType
    {
        return $this->restrictionType;
    }

    public function setRestrictionType(RestrictionType $restrictionType): Restriction
    {
        $this->restrictionType = $restrictionType;
        return $this;
    }

    public function getRestrictionMessage(): ?string
    {
        return $this->restrictionMessage;
    }

    public function setRestrictionMessage(?string $restrictionMessage): Restriction
    {
        $this->restrictionMessage = $restrictionMessage;
        return $this;
    }

    public function getRestrictionEndLayerType(): ?string
    {
        return $this->restrictionEndLayerType;
    }

    public function setRestrictionEndLayerType(?string $restrictionEndLayerType): Restriction
    {
        $this->restrictionEndLayerType = $restrictionEndLayerType;
        return $this;
    }

    public function getRestrictionValue(): ?float
    {
        return $this->restrictionValue;
    }

    public function setRestrictionValue(?float $restrictionValue): Restriction
    {
        $this->restrictionValue = $restrictionValue;
        return $this;
    }
}
