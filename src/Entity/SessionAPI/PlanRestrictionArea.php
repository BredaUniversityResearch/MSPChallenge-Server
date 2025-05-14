<?php

namespace App\src\Entity\SessionAPI;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class PlanRestrictionArea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planRestrictionAreaId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planRestrictionArea')]
    #[ORM\JoinColumn(name: 'plan_restriction_area_plan_id', referencedColumnName: 'plan_id')]
    private ?Plan $plan;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planRestrictionArea')]
    #[ORM\JoinColumn(name: 'plan_restriction_area_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $layer;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planRestrictionArea')]
    #[ORM\JoinColumn(name: 'plan_restriction_area_country_id', referencedColumnName: 'country_id')]
    private ?Country $country;

    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planRestrictionAreaEntityType;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $planRestrictionAreaSize;

    public function getPlanRestrictionAreaId(): ?int
    {
        return $this->planRestrictionAreaId;
    }

    public function setPlanRestrictionAreaId(?int $planRestrictionAreaId): PlanRestrictionArea
    {
        $this->planRestrictionAreaId = $planRestrictionAreaId;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): PlanRestrictionArea
    {
        $this->plan = $plan;
        return $this;
    }

    public function getLayer(): ?Layer
    {
        return $this->layer;
    }

    public function setLayer(?Layer $layer): PlanRestrictionArea
    {
        $this->layer = $layer;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): PlanRestrictionArea
    {
        $this->country = $country;
        return $this;
    }

    public function getPlanRestrictionAreaEntityType(): ?int
    {
        return $this->planRestrictionAreaEntityType;
    }

    public function setPlanRestrictionAreaEntityType(?int $planRestrictionAreaEntityType): PlanRestrictionArea
    {
        $this->planRestrictionAreaEntityType = $planRestrictionAreaEntityType;
        return $this;
    }

    public function getPlanRestrictionAreaSize(): ?float
    {
        return $this->planRestrictionAreaSize;
    }

    public function setPlanRestrictionAreaSize(?float $planRestrictionAreaSize): PlanRestrictionArea
    {
        $this->planRestrictionAreaSize = $planRestrictionAreaSize;
        return $this;
    }
}
