<?php

namespace App\src\Entity\SessionAPI;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class EnergyConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $energyConnectionId;

    #[ORM\ManyToOne(targetEntity: Geometry::class, cascade: ['persist'], inversedBy: 'energyConnectionStart')]
    #[ORM\JoinColumn(name: 'energy_connection_start_id', referencedColumnName: 'geometry_id')]
    private ?Geometry $startGeometry;

    #[ORM\ManyToOne(targetEntity: Geometry::class, cascade: ['persist'], inversedBy: 'energyConnectionEnd')]
    #[ORM\JoinColumn(name: 'energy_connection_end_id', referencedColumnName: 'geometry_id')]
    private ?Geometry $endGeometry;

    #[ORM\ManyToOne(targetEntity: Geometry::class, cascade: ['persist'], inversedBy: 'energyConnectionCable')]
    #[ORM\JoinColumn(name: 'energy_connection_cable_id', referencedColumnName: 'geometry_id')]
    private ?Geometry $cableGeometry;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $energyConnectionStartCoordinates;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $energyConnectionLastupdate;

    #[ORM\Column(type: Types::SMALLINT, length: 4, nullable: true, options: ['default' => 1])]
    private ?int $energyConnectionActive = 1;

    public function getEnergyConnectionId(): ?int
    {
        return $this->energyConnectionId;
    }

    public function setEnergyConnectionId(?int $energyConnectionId): EnergyConnection
    {
        $this->energyConnectionId = $energyConnectionId;
        return $this;
    }

    public function getStartGeometry(): ?Geometry
    {
        return $this->startGeometry;
    }

    public function setStartGeometry(?Geometry $startGeometry): EnergyConnection
    {
        $this->startGeometry = $startGeometry;
        return $this;
    }

    public function getEndGeometry(): ?Geometry
    {
        return $this->endGeometry;
    }

    public function setEndGeometry(?Geometry $endGeometry): EnergyConnection
    {
        $this->endGeometry = $endGeometry;
        return $this;
    }

    public function getCableGeometry(): ?Geometry
    {
        return $this->cableGeometry;
    }

    public function setCableGeometry(?Geometry $cableGeometry): EnergyConnection
    {
        $this->cableGeometry = $cableGeometry;
        return $this;
    }

    public function getEnergyConnectionStartCoordinates(): ?string
    {
        return $this->energyConnectionStartCoordinates;
    }

    public function setEnergyConnectionStartCoordinates(?string $energyConnectionStartCoordinates): EnergyConnection
    {
        $this->energyConnectionStartCoordinates = $energyConnectionStartCoordinates;
        return $this;
    }

    public function getEnergyConnectionLastupdate(): ?float
    {
        return $this->energyConnectionLastupdate;
    }

    public function setEnergyConnectionLastupdate(?float $energyConnectionLastupdate): EnergyConnection
    {
        $this->energyConnectionLastupdate = $energyConnectionLastupdate;
        return $this;
    }

    public function getEnergyConnectionActive(): ?int
    {
        return $this->energyConnectionActive;
    }

    public function setEnergyConnectionActive(?int $energyConnectionActive): EnergyConnection
    {
        $this->energyConnectionActive = $energyConnectionActive;
        return $this;
    }
}
