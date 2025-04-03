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

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Fishing::class, cascade: ['persist'])]
    private Collection $fishing;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: PlanMessage::class, cascade: ['persist'])]
    private Collection $planMessage;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: PlanRestrictionArea::class, cascade: ['persist'])]
    private Collection $planRestrictionArea;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Geometry::class, cascade: ['persist'])]
    private Collection $geometry;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: GridEnergy::class, cascade: ['persist'])]
    private Collection $gridEnergy;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'country', targetEntity: User::class, orphanRemoval: true)]
    private Collection $users;

    public function __construct()
    {
        $this->objective = new ArrayCollection();
        $this->plan = new ArrayCollection();
        $this->fishing = new ArrayCollection();
        $this->planMessage = new ArrayCollection();
        $this->planRestrictionArea = new ArrayCollection();
        $this->geometry = new ArrayCollection();
        $this->gridEnergy = new ArrayCollection();
        $this->users = new ArrayCollection();
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

    /**
     * @return Collection<int, Fishing>
     */
    public function getFishing(): Collection
    {
        return $this->fishing;
    }

    public function addFishing(Fishing $fishing): self
    {
        if (!$this->fishing->contains($fishing)) {
            $this->fishing->add($fishing);
            $fishing->setCountry($this);
        }

        return $this;
    }

    public function removeFishing(Fishing $fishing): self
    {
        if ($this->fishing->removeElement($fishing)) {
            // set the owning side to null (unless already changed)
            if ($fishing->getCountry() === $this) {
                $fishing->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanMessage>
     */
    public function getPlanMessage(): Collection
    {
        return $this->planMessage;
    }

    public function addPlanMessage(PlanMessage $planMessage): self
    {
        if (!$this->planMessage->contains($planMessage)) {
            $this->planMessage->add($planMessage);
            $planMessage->setCountry($this);
        }

        return $this;
    }

    public function removePlanMessage(PlanMessage $planMessage): self
    {
        if ($this->planMessage->removeElement($planMessage)) {
            // set the owning side to null (unless already changed)
            if ($planMessage->getCountry() === $this) {
                $planMessage->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanRestrictionArea>
     */
    public function getPlanRestrictionArea(): Collection
    {
        return $this->planRestrictionArea;
    }

    public function addPlanRestrictionArea(PlanRestrictionArea $planRestrictionArea): self
    {
        if (!$this->planRestrictionArea->contains($planRestrictionArea)) {
            $this->planRestrictionArea->add($planRestrictionArea);
            $planRestrictionArea->setCountry($this);
        }

        return $this;
    }

    public function removePlanRestrictionArea(PlanRestrictionArea $planRestrictionArea): self
    {
        if ($this->planRestrictionArea->removeElement($planRestrictionArea)) {
            // set the owning side to null (unless already changed)
            if ($planRestrictionArea->getCountry() === $this) {
                $planRestrictionArea->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Geometry>
     */
    public function getGeometry(): Collection
    {
        return $this->geometry;
    }

    public function addGeometry(Geometry $geometry): self
    {
        if (!$this->geometry->contains($geometry)) {
            $this->geometry->add($geometry);
            $geometry->setCountry($this);
        }

        return $this;
    }

    public function removeGeometry(Geometry $geometry): self
    {
        if ($this->geometry->removeElement($geometry)) {
            // set the owning side to null (unless already changed)
            if ($geometry->getCountry() === $this) {
                $geometry->setCountry(null);
            }
        }

        return $this;
    }

    public function getGridEnergy(): Collection
    {
        return $this->gridEnergy;
    }

    public function addGridEnergy(GridEnergy $gridEnergy): self
    {
        if (!$this->gridEnergy->contains($gridEnergy)) {
            $this->gridEnergy->add($gridEnergy);
            $gridEnergy->setCountry($this);
        }

        return $this;
    }

    public function removeGridEnergy(GridEnergy $gridEnergy): self
    {
        if ($this->gridEnergy->removeElement($gridEnergy)) {
            // set the owning side to null (unless already changed)
            if ($gridEnergy->getCountry() === $this) {
                $gridEnergy->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setUserCountry($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getUserCountry() === $this) {
                $user->setUserCountry(null);
            }
        }

        return $this;
    }
}
