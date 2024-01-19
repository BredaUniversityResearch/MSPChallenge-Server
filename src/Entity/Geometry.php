<?php

namespace App\Entity;

use App\Repository\GeometryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GeometryRepository::class)]

class Geometry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $geometryId;

    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $geometryLayerId;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $geometryPersistent;

    #[ORM\Column(type: Types::STRING, length: 75, nullable: true)]
    private ?string $geometryFID;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $geometryGeometry;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $geometryData;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $geometryCountryId;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $geometryActive = 1;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 0])]
    private ?int $geometrySubtractive = 0;

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => '0'])]
    private ?string $geometryType = '0';

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $geometryDeleted = 0;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $geometryMspid;

    public function getGeometryId(): ?int
    {
        return $this->geometryId;
    }

    public function setGeometryId(?int $geometryId): Geometry
    {
        $this->geometryId = $geometryId;
        return $this;
    }

    public function getGeometryLayerId(): ?int
    {
        return $this->geometryLayerId;
    }

    public function setGeometryLayerId(?int $geometryLayerId): Geometry
    {
        $this->geometryLayerId = $geometryLayerId;
        return $this;
    }

    public function getGeometryPersistent(): ?int
    {
        return $this->geometryPersistent;
    }

    public function setGeometryPersistent(?int $geometryPersistent): Geometry
    {
        $this->geometryPersistent = $geometryPersistent;
        return $this;
    }

    public function getGeometryFID(): ?string
    {
        return $this->geometryFID;
    }

    public function setGeometryFID(?string $geometryFID): Geometry
    {
        $this->geometryFID = $geometryFID;
        return $this;
    }

    public function getGeometryGeometry(): ?string
    {
        return $this->geometryGeometry;
    }

    public function setGeometryGeometry(?string $geometryGeometry): Geometry
    {
        $this->geometryGeometry = $geometryGeometry;
        return $this;
    }

    public function getGeometryData(): ?string
    {
        return $this->geometryData;
    }

    public function setGeometryData(?string $geometryData): Geometry
    {
        $this->geometryData = $geometryData;
        return $this;
    }

    public function getGeometryCountryId(): ?int
    {
        return $this->geometryCountryId;
    }

    public function setGeometryCountryId(?int $geometryCountryId): Geometry
    {
        $this->geometryCountryId = $geometryCountryId;
        return $this;
    }

    public function getGeometryActive(): ?int
    {
        return $this->geometryActive;
    }

    public function setGeometryActive(?int $geometryActive): Geometry
    {
        $this->geometryActive = $geometryActive;
        return $this;
    }

    public function getGeometrySubtractive(): ?int
    {
        return $this->geometrySubtractive;
    }

    public function setGeometrySubtractive(?int $geometrySubtractive): Geometry
    {
        $this->geometrySubtractive = $geometrySubtractive;
        return $this;
    }

    public function getGeometryType(): ?string
    {
        return $this->geometryType;
    }

    public function setGeometryType(?string $geometryType): Geometry
    {
        $this->geometryType = $geometryType;
        return $this;
    }

    public function getGeometryDeleted(): ?int
    {
        return $this->geometryDeleted;
    }

    public function setGeometryDeleted(?int $geometryDeleted): Geometry
    {
        $this->geometryDeleted = $geometryDeleted;
        return $this;
    }

    public function getGeometryMspid(): ?string
    {
        return $this->geometryMspid;
    }

    public function setGeometryMspid(?string $geometryMspid): Geometry
    {
        $this->geometryMspid = $geometryMspid;
        return $this;
    }
}
