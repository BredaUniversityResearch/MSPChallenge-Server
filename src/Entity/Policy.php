<?php

namespace App\Entity;

use App\Repository\PolicyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSchema\Validator;

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
     * @var mixed $data this will be a json string with the data for the policy
     */
    #[ORM\Column(type: 'json', nullable: true, options: ['default' => 'NULL'])]
    private mixed $data = null;

    #[ORM\OneToMany(
        mappedBy: 'policy',
        targetEntity: PlanPolicy::class,
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    private Collection $planPolicies;

    #[ORM\OneToMany(
        mappedBy: 'policy',
        targetEntity: PolicyLayer::class,
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    private Collection $policyLayers;

    public function __construct()
    {
        $this->planPolicies = new ArrayCollection();
        $this->policyLayers = new ArrayCollection();
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

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): static
    {
        $this->data = $data;

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

    public function hasFleetFiltersMatch(int $fleet): ?bool
    {
        $validator = new Validator();
        foreach ($this->getType()->getPolicyTypeFilterTypes() as $policyTypeFilterType) {
            if ($policyTypeFilterType->getPolicyFilterType()->getName() !== 'fleet') {
                continue;
            }
            // no fleet filter data found
            if (!isset($this->data['fleets'])) {
                return null;
            }
            // data should not match the required schema
            $obj = (object)$this->data;
            $validator->validate($obj, $policyTypeFilterType->getPolicyFilterType()->getSchema());
            if (!$validator->isValid()) {
                return null;
            }
            // false if there is no fleet filter matching the geometry type
            return $fleet == ($this->data['fleets'] & $fleet);
        }
        // no filters fleet relation found
        return null;
    }

    public function hasScheduleFiltersMatch(int $currentMonth): ?bool
    {
        $validator = new Validator();
        foreach ($this->getType()->getPolicyTypeFilterTypes() as $policyTypeFilterType) {
            if ($policyTypeFilterType->getPolicyFilterType()->getName() !== 'schedule') {
                continue;
            }
            // no filters schedule data found
            if (!isset($this->data['months'])) {
                return null;
            }
            // data should not match the required schema
            $obj = (object)$this->data;
            $validator->validate($obj, $policyTypeFilterType->getPolicyFilterType()->getSchema());
            if (!$validator->isValid()) {
                return null;
            }
            // false if there is no a seasonal closure for this month, so no pressures
            return in_array(($currentMonth % 12) + 1, $this->data['months']);
        }
         // no filters schedule relation found
        return null;
    }
}
