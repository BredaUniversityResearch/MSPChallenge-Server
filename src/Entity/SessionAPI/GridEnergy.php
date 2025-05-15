<?php

namespace App\Entity\SessionAPI;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class GridEnergy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $gridEnergyId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'gridEnergy')]
    #[ORM\JoinColumn(name: 'grid_energy_grid_id', referencedColumnName: 'grid_id')]
    private ?Grid $grid;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'gridEnergy')]
    #[ORM\JoinColumn(name: 'grid_energy_country_id', referencedColumnName: 'country_id')]
    private ?Country $country;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['default' => 0])]
    private ?string $gridEnergyExpected;

    public function getGridEnergyId(): ?int
    {
        return $this->gridEnergyId;
    }

    public function setGridEnergyId(?int $gridEnergyId): GridEnergy
    {
        $this->gridEnergyId = $gridEnergyId;
        return $this;
    }

    public function getGrid(): ?Grid
    {
        return $this->grid;
    }

    public function setGrid(?Grid $grid): GridEnergy
    {
        $this->grid = $grid;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): GridEnergy
    {
        $this->country = $country;
        return $this;
    }

    public function getGridEnergyExpected(): ?string
    {
        return $this->gridEnergyExpected;
    }

    public function setGridEnergyExpected(?string $gridEnergyExpected): GridEnergy
    {
        $this->gridEnergyExpected = $gridEnergyExpected;
        return $this;
    }
}
