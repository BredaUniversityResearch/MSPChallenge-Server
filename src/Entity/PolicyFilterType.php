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

    // just use https://transform.tools/json-to-json-schema to create a json schema
    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $schema = null;

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

    public function getSchema(): mixed
    {
        return $this->schema;
    }

    public function setSchema(mixed $schema): static
    {
        $this->schema = $schema;
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
