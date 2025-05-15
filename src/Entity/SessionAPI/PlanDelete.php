<?php

namespace App\Entity\SessionAPI;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PlanDelete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    // @phpstan-ignore-next-line (ignore unused)
    private ?int $planDeleteId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planDelete')]
    #[ORM\JoinColumn(name: 'plan_delete_plan_id', referencedColumnName: 'plan_id')]
    private ?Plan $plan;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planDelete')]
    #[ORM\JoinColumn(name: 'plan_delete_geometry_persistent', referencedColumnName: 'geometry_id')]
    private ?Geometry $geometry;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planDelete')]
    #[ORM\JoinColumn(name: 'plan_delete_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $layer;

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): PlanDelete
    {
        $this->plan = $plan;
        return $this;
    }

    public function getGeometry(): ?Geometry
    {
        return $this->geometry;
    }

    public function setGeometry(?Geometry $geometry): PlanDelete
    {
        $this->geometry = $geometry;
        return $this;
    }

    public function getLayer(): ?Layer
    {
        return $this->layer;
    }

    public function setLayer(?Layer $layer): PlanDelete
    {
        $this->layer = $layer;
        return $this;
    }
}
