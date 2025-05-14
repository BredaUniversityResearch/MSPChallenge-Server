<?php

namespace App\src\Entity\SessionAPI;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class EnergyOutput
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $energyOutputId;

    #[ORM\ManyToOne(targetEntity: Geometry::class, cascade: ['persist'], inversedBy: 'energyOutput')]
    #[ORM\JoinColumn(name: 'energy_output_geometry_id', referencedColumnName: 'geometry_id')]
    private ?Geometry $geometry;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 0])]
    private ?string $energyOutputCapacity = '0';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 0])]
    private ?string $energyOutputMaxcapacity = '0';

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private ?float $energyOutputLastupdate = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 1])]
    private ?int $energyOutputActive;

    public function getEnergyOutputId(): ?int
    {
        return $this->energyOutputId;
    }

    public function setEnergyOutputId(?int $energyOutputId): EnergyOutput
    {
        $this->energyOutputId = $energyOutputId;
        return $this;
    }

    public function getGeometry(): ?Geometry
    {
        return $this->geometry;
    }

    public function setGeometry(?Geometry $geometry): EnergyOutput
    {
        $this->geometry = $geometry;
        return $this;
    }

    public function getEnergyOutputCapacity(): ?string
    {
        return $this->energyOutputCapacity;
    }

    public function setEnergyOutputCapacity(?string $energyOutputCapacity): EnergyOutput
    {
        $this->energyOutputCapacity = $energyOutputCapacity;
        return $this;
    }

    public function getEnergyOutputMaxcapacity(): ?string
    {
        return $this->energyOutputMaxcapacity;
    }

    public function setEnergyOutputMaxcapacity(?string $energyOutputMaxcapacity): EnergyOutput
    {
        $this->energyOutputMaxcapacity = $energyOutputMaxcapacity;
        return $this;
    }

    public function getEnergyOutputLastupdate(): ?float
    {
        return $this->energyOutputLastupdate;
    }

    public function setEnergyOutputLastupdate(?float $energyOutputLastupdate): EnergyOutput
    {
        $this->energyOutputLastupdate = $energyOutputLastupdate;
        return $this;
    }

    public function getEnergyOutputActive(): ?int
    {
        return $this->energyOutputActive;
    }

    public function setEnergyOutputActive(?int $energyOutputActive): EnergyOutput
    {
        $this->energyOutputActive = $energyOutputActive;
        return $this;
    }
}
