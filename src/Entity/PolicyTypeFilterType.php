<?php

namespace App\Entity;

use App\Repository\PolicyTypeFilterTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyTypeFilterTypeRepository::class)]
#[
    ORM\UniqueConstraint(
        name: 'policy_type_id_policy_filter_type_id',
        columns: ['policy_type_id', 'policy_filter_type_id']
    )
]
class PolicyTypeFilterType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'policyTypeFilterTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PolicyType $policyType = null;

    #[ORM\ManyToOne(inversedBy: 'policyTypeFilterTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PolicyFilterType $policyFilterType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPolicyType(): ?PolicyType
    {
        return $this->policyType;
    }

    public function setPolicyType(PolicyType $policyType): static
    {
        $this->policyType = $policyType;
        $this->policyType->addPolicyTypeFilterType($this);
        return $this;
    }

    public function getPolicyFilterType(): ?PolicyFilterType
    {
        return $this->policyFilterType;
    }

    public function setPolicyFilterType(PolicyFilterType $policyFilterType): static
    {
        $this->policyFilterType = $policyFilterType;
        $this->policyFilterType->addPolicyTypeFilterType($this);
        return $this;
    }
}
