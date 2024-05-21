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
        // todo : rewrite based on "data"
//        $policyFilterLinks = $this->getPolicyFilterLinks()->toArray();
//        /** @var PolicyFilterLink[] $fleetFilters */
//        $fleetFilters = collect($policyFilterLinks)
//            ->filter(fn($pfl) => $pfl->getPolicyFilter()->getType()->getName() === 'fleet')->all();
//        if (empty($fleetFilters)) {
//            return null; // no fleet filters found
//        }
//        // if there is no fleet filter matching the geometry type
//        if (false === array_reduce(
//            $fleetFilters,
//            fn($carry, PolicyFilterLink $item) => $carry ||
//                (($item->getPolicyFilter()->getValue() & $fleet) == $fleet),
//            false
//        )) {
//            return false; // no there is no match
//        }
//        return true;
        return false;
    }

    public function hasScheduleFiltersMatch(int $currentMonth): ?bool
    {
        // todo : rewrite based on "data"
//        $policyFilterLinks = $this->getPolicyFilterLinks()->toArray();
//        /** @var PolicyFilterLink[] $scheduleFilters */
//        $scheduleFilters = collect($policyFilterLinks)
//            ->filter(
//                fn(PolicyFilterLink $pfl) => $pfl->getPolicyFilter()->getType()->getName() === 'schedule'
//            )
//            ->all();
//        if (empty($scheduleFilters)) {
//            return null; // no filters schedule found
//        }
//        // is there any seasonal filter matching the current game month?
//        if (false === array_reduce(
//            $scheduleFilters,
//            fn($carry, PolicyFilterLink $item) => $carry ||
//                // convert "number of months" to a month number 1-12
//                in_array(($currentMonth % 12) + 1, $item->getPolicyFilter()->getValue()),
//            false
//        )) {
//            return false; // meaning there should not be a seasonal closure for this month, so no pressures
//        }
//        return true;
        return false;
    }
}
