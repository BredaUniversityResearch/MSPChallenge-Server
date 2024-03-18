<?php

namespace App\Entity;

use App\Repository\PolicyFilterLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyFilterLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'policy_id_policy_filter_id', columns: ['policy_id', 'policy_filter_id'])]
class PolicyFilterLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'policyFilterLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Policy $policy = null;

    #[ORM\ManyToOne(inversedBy: 'policyFilterLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PolicyFilter $policyFilter = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPolicy(): ?Policy
    {
        return $this->policy;
    }

    public function setPolicy(Policy $policy): static
    {
        $this->policy = $policy;
        $this->policy->addPolicyFilterLink($this);
        return $this;
    }

    public function getPolicyFilter(): ?PolicyFilter
    {
        return $this->policyFilter;
    }

    public function setPolicyFilter(PolicyFilter $policyFilter): static
    {
        $this->policyFilter = $policyFilter;
        $this->policyFilter->addPolicyFilterLink($this);
        return $this;
    }
}
