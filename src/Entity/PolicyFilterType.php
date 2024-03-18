<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\FieldType;
use App\Repository\PolicyFilterTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyFilterTypeRepository::class)]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class PolicyFilterType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, enumType: FieldType::class)]
    private FieldType $fieldType = FieldType::JSON;

    // just use https://transform.tools/json-to-json-schema to create a json schema
    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $fieldSchema = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }

    public function setFieldType(FieldType $fieldType): static
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getFieldSchema(): mixed
    {
        return $this->fieldSchema;
    }

    public function setFieldSchema(mixed $fieldSchema): static
    {
        $this->fieldSchema = $fieldSchema;
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
