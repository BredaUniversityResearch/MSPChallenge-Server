<?php

namespace App\Entity\SessionAPI;

use App\src\Repository\SessionAPI\PlanPolicyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanPolicyRepository::class)]
#[ORM\UniqueConstraint(name: 'plan_id_policy_id', columns: ['plan_id', 'policy_id'])]
class PlanPolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'planPolicies')]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'plan_id', nullable: false, onDelete: 'CASCADE')]
    private ?Plan $plan = null;

    #[ORM\ManyToOne(inversedBy: 'planPolicies')]
    #[ORM\JoinColumn(name: 'policy_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Policy $policy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): static
    {
        $this->plan = $plan;
        $this->plan->addPlanPolicy($this);
        return $this;
    }

    public function getPolicy(): ?Policy
    {
        return $this->policy;
    }

    public function setPolicy(Policy $policy): static
    {
        $this->policy = $policy;
        $this->policy->addPlanPolicy($this);
        return $this;
    }
}
