<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use App\Domain\PolicyData\FleetFilterPolicyData;
use App\Domain\PolicyData\PolicyDataMetaName;
use App\Domain\PolicyData\PolicyGroup;
use App\Domain\PolicyData\ScheduleFilterPolicyData;
use App\Repository\PolicyFilterTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;

#[ORM\Entity(repositoryClass: PolicyFilterTypeRepository::class)]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class PolicyFilterType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, enumType: PolicyFilterTypeName::class)]
    private PolicyFilterTypeName $name = PolicyFilterTypeName::SCHEDULE;
    #[
        ORM\OneToMany(
            mappedBy: 'policyFilterType',
            targetEntity: PolicyTypeFilterType::class,
            cascade: ['persist'],
            orphanRemoval: true
        )
    ]
    private Collection $policyTypeFilterTypes;

    public function __construct()
    {
        $this->policyTypeFilterTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): PolicyFilterTypeName
    {
        return $this->name;
    }

    public function setName(PolicyFilterTypeName $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @throws InvalidValue
     */
    public function getSchema(): object
    {
        $schemaWrapper = match ($this->name) {
            PolicyFilterTypeName::FLEET => FleetFilterPolicyData::schema(),
            PolicyFilterTypeName::SCHEDULE => ScheduleFilterPolicyData::schema()
        };
        assert($schemaWrapper->getMeta(PolicyDataMetaName::GROUP->value) == PolicyGroup::FILTER);
        assert($schemaWrapper->getMeta(PolicyDataMetaName::TYPE_NAME->value) == $this->name);
        return Schema::export($schemaWrapper);
    }

    /**
     * @return Collection<int, PolicyTypeFilterType>
     */
    public function getPolicyTypeFilterTypes(): Collection
    {
        return $this->policyTypeFilterTypes;
    }

    public function addPolicyTypeFilterType(PolicyTypeFilterType $policyTypeFilterType): static
    {
        if (!$this->policyTypeFilterTypes->contains($policyTypeFilterType)) {
            $this->policyTypeFilterTypes->add($policyTypeFilterType);
            $policyTypeFilterType->setPolicyFilterType($this);
        }

        return $this;
    }

    public function removePolicyTypeFilterType(PolicyTypeFilterType $policyTypeFilterType): static
    {
        $this->policyTypeFilterTypes->removeElement($policyTypeFilterType);
        // Since orphanRemoval is set, no need to explicitly remove $policyTypeFilterType from the database
        return $this;
    }
}
