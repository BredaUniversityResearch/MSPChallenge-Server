<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use App\Repository\PolicyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PolicyRepository::class)]
class Policy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, enumType: PolicyTypeName::class)]
    private PolicyTypeName $type = PolicyTypeName::SEASONAL_CLOSURE;

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

    public function getType(): PolicyTypeName
    {
        return $this->type;
    }

    public function setType(PolicyTypeName $type): static
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
}
