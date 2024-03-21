<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class PlanLayer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planLayerId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planLayer')]
    #[ORM\JoinColumn(name: 'plan_layer_plan_id', referencedColumnName: 'plan_id')]
    private ?Plan $plan;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planLayer')]
    #[ORM\JoinColumn(name: 'plan_layer_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $layer;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['default' => 'WAIT'])]
    private ?string $planLayerState = 'WAIT';

    public function getPlanLayerId(): ?int
    {
        return $this->planLayerId;
    }

    public function setPlanLayerId(?int $planLayerId): PlanLayer
    {
        $this->planLayerId = $planLayerId;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): PlanLayer
    {
        $this->plan = $plan;
        $this->plan->addPlanLayer($this);
        return $this;
    }

    public function getLayer(): ?Layer
    {
        return $this->layer;
    }

    public function setLayer(Layer $layer): PlanLayer
    {
        $this->layer = $layer;
        $this->layer->addPlanLayer($this);
        return $this;
    }

    public function getPlanLayerState(): ?string
    {
        return $this->planLayerState;
    }

    public function setPlanLayerState(?string $planLayerState): PlanLayer
    {
        $this->planLayerState = $planLayerState;
        return $this;
    }
}
