<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Grid
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $gridId;

    #[ORM\Column(type: Types::STRING, length: 75, nullable: true)]
    private ?string $gridName;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $gridLastupdate;

    #[ORM\Column(type: Types::SMALLINT, length: 4, nullable: true, options: ['default' => 1])]
    private ?int $gridActive = 1;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'grid_plan_id', referencedColumnName: 'plan_id')]
    private ?Plan $plan;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'derivedGrid')]
    #[ORM\JoinColumn(name: 'grid_persistent', referencedColumnName: 'grid_id')]
    private ?Grid $originalGrid;

    #[ORM\OneToMany(mappedBy: 'originalGrid', targetEntity: Grid::class, cascade: ['persist'])]
    private Collection $derivedGrid;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $gridDistributionOnly = 0;

    #[ORM\OneToMany(mappedBy: 'grid', targetEntity: GridEnergy::class, cascade: ['persist'])]
    private Collection $gridEnergy;

    #[ORM\ManyToMany(targetEntity: Plan::class, mappedBy: 'gridToRemove', cascade: ['persist'])]
    private Collection $planToRemove;

    #[ORM\JoinTable(name: 'grid_source')]
    #[ORM\JoinColumn(name: 'grid_source_grid_id', referencedColumnName: 'grid_id')]
    #[ORM\InverseJoinColumn(name: 'grid_source_geometry_id', referencedColumnName: 'geometry_id')]
    #[ORM\ManyToMany(targetEntity: Geometry::class, inversedBy: 'sourceForGrid', cascade: ['persist'])]
    private Collection $sourceGeometry;

    #[ORM\JoinTable(name: 'grid_socket')]
    #[ORM\JoinColumn(name: 'grid_socket_grid_id', referencedColumnName: 'grid_id')]
    #[ORM\InverseJoinColumn(name: 'grid_socket_geometry_id', referencedColumnName: 'geometry_id')]
    #[ORM\ManyToMany(targetEntity: Geometry::class, inversedBy: 'socketForGrid', cascade: ['persist'])]
    private Collection $socketGeometry;

    public function __construct()
    {
        $this->derivedGrid = new ArrayCollection();
        $this->gridEnergy = new ArrayCollection();
        $this->planToRemove = new ArrayCollection();
        $this->sourceGeometry = new ArrayCollection();
        $this->socketGeometry = new ArrayCollection();
    }

    public function getGridId(): ?int
    {
        return $this->gridId;
    }

    public function setGridId(?int $gridId): Grid
    {
        $this->gridId = $gridId;
        return $this;
    }

    public function getGridName(): ?string
    {
        return $this->gridName;
    }

    public function setGridName(?string $gridName): Grid
    {
        $this->gridName = $gridName;
        return $this;
    }

    public function getGridLastupdate(): ?float
    {
        return $this->gridLastupdate;
    }

    public function setGridLastupdate(?float $gridLastupdate): Grid
    {
        $this->gridLastupdate = $gridLastupdate;
        return $this;
    }

    public function getGridActive(): ?int
    {
        return $this->gridActive;
    }

    public function setGridActive(?int $gridActive): Grid
    {
        $this->gridActive = $gridActive;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): Grid
    {
        $this->plan = $plan;
        return $this;
    }

    public function getOriginalGrid(): ?Grid
    {
        return $this->originalGrid;
    }

    public function setOriginalGrid(?Grid $originalGrid): Grid
    {
        $this->originalGrid = $originalGrid;
        return $this;
    }

    public function getGridDistributionOnly(): ?int
    {
        return $this->gridDistributionOnly;
    }

    public function setGridDistributionOnly(?int $gridDistributionOnly): Grid
    {
        $this->gridDistributionOnly = $gridDistributionOnly;
        return $this;
    }

    public function getDerivedGrid(): Collection
    {
        return $this->derivedGrid;
    }

    public function addDerivedGrid(Grid $derivedGrid): Grid
    {
        if (!$this->derivedGrid->contains($derivedGrid)) {
            $this->derivedGrid->add($derivedGrid);
            $derivedGrid->setOriginalGrid($this);
        }

        return $this;
    }

    public function removeDerivedGrid(Grid $derivedGrid): Grid
    {
        if ($this->derivedGrid->removeElement($derivedGrid)) {
            // set the owning side to null (unless already changed)
            if ($derivedGrid->getOriginalGrid() === $this) {
                $derivedGrid->setOriginalGrid(null);
            }
        }

        return $this;
    }

    public function getGridEnergy(): Collection
    {
        return $this->gridEnergy;
    }

    public function addGridEnergy(GridEnergy $gridEnergy): Grid
    {
        if (!$this->gridEnergy->contains($gridEnergy)) {
            $this->gridEnergy->add($gridEnergy);
            $gridEnergy->setGrid($this);
        }

        return $this;
    }

    public function removeGridEnergy(GridEnergy $gridEnergy): Grid
    {
        if ($this->gridEnergy->removeElement($gridEnergy)) {
            // set the owning side to null (unless already changed)
            if ($gridEnergy->getGrid() === $this) {
                $gridEnergy->setGrid(null);
            }
        }

        return $this;
    }

    public function getPlanToRemove(): Collection
    {
        return $this->planToRemove;
    }

    public function addPlanToRemove(Plan $planToRemove): self
    {
        if (!$this->planToRemove->contains($planToRemove)) {
            $this->planToRemove->add($planToRemove);
            $planToRemove->addGridToRemove($this);
        }

        return $this;
    }

    public function removePlanToRemove(Plan $planToRemove): self
    {
        if ($this->planToRemove->removeElement($planToRemove)) {
            $planToRemove->removeGridToRemove($this);
        }

        return $this;
    }

    public function getSourceGeometry(): Collection
    {
        return $this->sourceGeometry;
    }

    public function addSourceGeometry(Geometry $sourceGeometry): self
    {
        if (!$this->sourceGeometry->contains($sourceGeometry)) {
            $this->sourceGeometry->add($sourceGeometry);
            $sourceGeometry->addSourceForGrid($this);
        }

        return $this;
    }

    public function removeSourceGeometry(Geometry $sourceGeometry): self
    {
        if ($this->sourceGeometry->removeElement($sourceGeometry)) {
            $sourceGeometry->removeSourceForGrid($this);
        }

        return $this;
    }

    public function getSocketGeometry(): Collection
    {
        return $this->socketGeometry;
    }

    public function addSocketGeometry(Geometry $socketGeometry): self
    {
        if (!$this->socketGeometry->contains($socketGeometry)) {
            $this->socketGeometry->add($socketGeometry);
            $socketGeometry->addSocketForGrid($this);
        }

        return $this;
    }

    public function removeSocketGeometry(Geometry $socketGeometry): self
    {
        if ($this->socketGeometry->removeElement($socketGeometry)) {
            $socketGeometry->removeSocketForGrid($this);
        }

        return $this;
    }
}
