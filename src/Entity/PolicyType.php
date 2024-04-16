<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyTypeDataType;
use App\Repository\PolicyTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyTypeRepository::class)]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class PolicyType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $displayName = null;

    #[ORM\Column(length: 255, enumType: PolicyTypeDataType::class)]
    private PolicyTypeDataType $dataType = PolicyTypeDataType::Boolean;

    /**
     * @var mixed $dataConfig this will be a json with the configuration for the data type,
     *   or null if not needed
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $dataConfig = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
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

    public function getDataType(): PolicyTypeDataType
    {
        return $this->dataType;
    }

    public function setDataType(PolicyTypeDataType $dataType): static
    {
        $this->dataType = $dataType;

        return $this;
    }

    public function getDataConfig(): mixed
    {
        return $this->dataConfig;
    }

    public function setDataConfig(mixed $dataConfig): static
    {
        $this->dataConfig = $dataConfig;

        return $this;
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
