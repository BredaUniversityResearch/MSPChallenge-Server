<?php

namespace App\Entity;

use App\Repository\PolicyFilterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyFilterRepository::class)]
class PolicyFilter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne()]
    #[ORM\JoinColumn(nullable: false)]
    private ?PolicyFilterType $type = null;

    #[ORM\Column(type: 'json')]
    private mixed $value = null;

    #[
        ORM\OneToMany(
            mappedBy: 'policyFilter',
            targetEntity: PolicyFilterLink::class,
            cascade: ['persist'],
            orphanRemoval: true
        )
    ]
    private Collection $policyFilterLinks;

    public function __construct()
    {
        $this->policyFilterLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?PolicyFilterType
    {
        return $this->type;
    }

    public function setType(?PolicyFilterType $type): static
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
            $policyFilterLink->setPolicyFilter($this);
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
