<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $countryId;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $countryName;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $countryColour;

    #[ORM\Column(type: Types::INTEGER, length: 1, nullable: true, options: ['default' => 0])]
    private ?int $countryIsManager;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Objective::class, cascade: ['persist'])]
    private Collection $objective;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Plan::class, cascade: ['persist'])]
    private Collection $plan;

    public function __construct()
    {
        $this->objective = new ArrayCollection();
    }

    public function getCountryId(): ?int
    {
        return $this->countryId;
    }

    public function setCountryId(?int $countryId): Country
    {
        $this->countryId = $countryId;
        return $this;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function setCountryName(?string $countryName): Country
    {
        $this->countryName = $countryName;
        return $this;
    }

    public function getCountryColour(): ?string
    {
        return $this->countryColour;
    }

    public function setCountryColour(?string $countryColour): Country
    {
        $this->countryColour = $countryColour;
        return $this;
    }

    public function getCountryIsManager(): ?int
    {
        return $this->countryIsManager;
    }

    public function setCountryIsManager(?int $countryIsManager): Country
    {
        $this->countryIsManager = $countryIsManager;
        return $this;
    }

    /**
     * @return Collection<int, Objective>
     */
    public function getObjective(): Collection
    {
        return $this->objective;
    }

    public function addObjective(Objective $objective): self
    {
        if (!$this->objective->contains($objective)) {
            $this->objective->add($objective);
            $objective->setCountry($this);
        }

        return $this;
    }

    public function removeObjective(Objective $objective): self
    {
        if ($this->objective->removeElement($objective)) {
            // set the owning side to null (unless already changed)
            if ($objective->getCountry() === $this) {
                $objective->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Plan>
     */
    public function getPlan(): Collection
    {
        return $this->plan;
    }

    public function addPlan(Plan $plan): self
    {
        if (!$this->plan->contains($plan)) {
            $this->plan->add($plan);
            $plan->setCountry($this);
        }

        return $this;
    }

    public function removePlan(Plan $plan): self
    {
        if ($this->plan->removeElement($plan)) {
            // set the owning side to null (unless already changed)
            if ($plan->getCountry() === $this) {
                $plan->setCountry(null);
            }
        }

        return $this;
    }

}
