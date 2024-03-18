<?php

namespace App\Entity;

use App\Repository\PolicyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyRepository::class)]
class Policy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PolicyType $type = null;

    /**
     * @var mixed $value this will be a json string with the value for the policy
     */
    #[ORM\Column(type: 'json')]
    private mixed $value = null;

    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: PlanPolicy::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $planPolicies;

    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: PolicyLayer::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $policyLayers;

    #[
        ORM\OneToMany(
            mappedBy: 'policy',
            targetEntity: PolicyFilterLink::class,
            cascade: ['persist'],
            orphanRemoval: true
        )
    ]
    private Collection $policyFilterLinks;

    public function __construct()
    {
        $this->planPolicies = new ArrayCollection();
        $this->policyLayers = new ArrayCollection();
        $this->policyFilterLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?PolicyType
    {
        return $this->type;
    }

    public function setType(?PolicyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return Collection<int, PlanPolicy>
     */
    public function getPlanPolicies(): Collection
    {
        return $this->planPolicies;
    }

    public function addPlanPolicy(PlanPolicy $planPolicy): static
    {
        if (!$this->planPolicies->contains($planPolicy)) {
            $this->planPolicies->add($planPolicy);
            $planPolicy->setPolicy($this);
        }

        return $this;
    }

    public function removePlanPolicy(PlanPolicy $planPolicy): static
    {
        $this->planPolicies->removeElement($planPolicy);
        // Since orphanRemoval is set, no need to explicitly remove $planPolicy from the database
        return $this;
    }

    /**
     * @return Collection<int, PolicyLayer>
     */
    public function getPolicyLayers(): Collection
    {
        return $this->policyLayers;
    }

    public function addPolicyLayer(PolicyLayer $policyLayer): static
    {
        if (!$this->policyLayers->contains($policyLayer)) {
            $this->policyLayers->add($policyLayer);
            $policyLayer->setPolicy($this);
        }

        return $this;
    }

    public function removePolicyLayer(PolicyLayer $policyLayer): static
    {
        $this->policyLayers->removeElement($policyLayer);
        // Since orphanRemoval is set, no need to explicitly remove $policyLayer from the database
        return $this;
    }

    /**
     * @return Collection<int, PolicyFilterLink>
     */
    public function getPolicyFilterLinks(): Collection
    {
        return $this->policyFilterLinks;
    }

    public function addPolicyFilterLink(PolicyFilterLink $policyFilterLink): static
    {
        if (!$this->policyFilterLinks->contains($policyFilterLink)) {
            $this->policyFilterLinks->add($policyFilterLink);
            $policyFilterLink->setPolicy($this);
        }

        return $this;
    }

    public function removePolicyFilterLink(PolicyFilterLink $policyFilterLink): static
    {
        $this->policyFilterLinks->removeElement($policyFilterLink);
        // Since orphanRemoval is set, no need to explicitly remove $policyFilterLink from the database
        return $this;
    }
}
