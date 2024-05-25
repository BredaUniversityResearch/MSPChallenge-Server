<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use App\Domain\PolicyData\BufferZonePolicyData;
use App\Domain\PolicyData\EcoGearPolicyData;
use App\Domain\PolicyData\PolicyDataMetaName;
use App\Domain\PolicyData\PolicyGroup;
use App\Domain\PolicyData\SeasonalClosurePolicyData;
use App\Repository\PolicyTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;

#[ORM\Entity(repositoryClass: PolicyTypeRepository::class)]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class PolicyType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, enumType: PolicyTypeName::class)]
    private PolicyTypeName $name = PolicyTypeName::SEASONAL_CLOSURE;

    #[ORM\Column(length: 255)]
    private ?string $displayName = null;

    #[
        ORM\OneToMany(
            mappedBy: 'policyType',
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

    public function getName(): PolicyTypeName
    {
        return $this->name;
    }

    public function setName(PolicyTypeName $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @throws InvalidValue
     */
    public function getSchema(): object
    {
        $schemaWrapper = match ($this->name) {
            PolicyTypeName::BUFFER_ZONE => BufferZonePolicyData::schema(),
            PolicyTypeName::SEASONAL_CLOSURE => SeasonalClosurePolicyData::schema(),
            PolicyTypeName::ECO_GEAR => EcoGearPolicyData::schema()
        };
        assert($schemaWrapper->getMeta(PolicyDataMetaName::GROUP->value) == PolicyGroup::POLICY);
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
            $policyTypeFilterType->setPolicyType($this);
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
