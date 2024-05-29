<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use App\Domain\Common\LogContainer;
use App\Domain\PolicyData\FleetFilterPolicyData;
use App\Domain\PolicyData\ScheduleFilterPolicyData;
use App\Repository\PolicyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\InvalidValue;

#[ORM\Entity(repositoryClass: PolicyRepository::class)]
class Policy extends LogContainer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PolicyType $type = null;

    /**
     * @var mixed $data can contain anything: array, objects, nested objects...
     * see https://github.com/dunglas/doctrine-json-odm
     * see https://www.doctrine-project.org/projects/doctrine-dbal/en/4.0/reference/types.html#json
     */
    #[ORM\Column(type: 'json_document', nullable: true, options: ['default' => 'NULL'])]
    private mixed $data = null;

    #[ORM\OneToMany(
        mappedBy: 'policy',
        targetEntity: PlanPolicy::class,
        cascade: ['persist','remove'],
        orphanRemoval: true
    )]
    private Collection $planPolicies;

    public function __construct()
    {
        $this->planPolicies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?PolicyType
    {
        return $this->type;
    }

    public function setType(PolicyType $type): static
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

    public function hasFleetFiltersMatch(int $fleet): ?bool
    {
        foreach ($this->getType()->getPolicyTypeFilterTypes() as $policyTypeFilterType) {
            if ($policyTypeFilterType->getPolicyFilterType()->getName() !== PolicyFilterTypeName::FLEET) {
                continue;
            }
            // data does not match the required schema
            try {
                /** @var FleetFilterPolicyData $data */
                $data = FleetFilterPolicyData::import($this->data);
            } catch (\Exception|InvalidValue $e) {
                // data does not match the required schema
                $this->log('No fleet filter schema found: '.$e->getMessage());
                return null;
            }
            // false if there is no fleet filter matching the geometry type
            return in_array($fleet, $data->fleets ?? []);
        }
        // no filters fleet relation found
        return null;
    }

    public function hasScheduleFiltersMatch(int $currentMonth): ?bool
    {
        foreach ($this->getType()->getPolicyTypeFilterTypes() as $policyTypeFilterType) {
            if ($policyTypeFilterType->getPolicyFilterType()->getName() !== PolicyFilterTypeName::SCHEDULE) {
                continue;
            }
            // data does not match the required schema
            try {
                /** @var ScheduleFilterPolicyData $data */
                $data = ScheduleFilterPolicyData::import($this->data);
            } catch (\Exception|InvalidValue $e) {
                // data does not match the required schema
                $this->log('No schedule filter schema found: '.$e->getMessage());
                return null;
            }
            // false if there is no a seasonal closure for this month, so no pressures
            $currentMonth = ($currentMonth % 12) + 1;
            return ($data->months & $currentMonth) === $currentMonth;
        }
         // no filters schedule relation found
        return null;
    }
}
