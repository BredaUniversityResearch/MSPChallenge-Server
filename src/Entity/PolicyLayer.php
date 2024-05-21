<?php

namespace App\Entity;

use App\Repository\PolicyLayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyLayerRepository::class)]
#[ORM\UniqueConstraint(name: 'policy_id_layer_id', columns: ['policy_id', 'layer_id'])]
class PolicyLayer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'policyLayers')]
    #[ORM\JoinColumn(name: 'policy_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Policy $policy = null;

    #[ORM\ManyToOne(inversedBy: 'policyLayers')]
    #[ORM\JoinColumn(name: 'layer_id', referencedColumnName: 'layer_id', nullable: false, onDelete: 'CASCADE')]
    private ?Layer $layer = null;

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
        $this->policy->addPolicyLayer($this);
        return $this;
    }

    public function getLayer(): ?Layer
    {
        return $this->layer;
    }

    public function setLayer(Layer $layer): static
    {
        $this->layer = $layer;
        $this->layer->addPolicyLayer($this);
        return $this;
    }
}
