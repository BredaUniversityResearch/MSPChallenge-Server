<?php

namespace App\Entity\SessionAPI;

use App\Domain\Common\EntityEnums\PlanState;
use App\Domain\Common\EntityEnums\PolicyTypeValue;
use App\Repository\SessionAPI\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'plan')]
    #[ORM\JoinColumn(name: 'plan_country_id', referencedColumnName: 'country_id')]
    private ?Country $country = null;

    #[ORM\Column(type: Types::STRING, length: 75)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $planName = null;

    #[ORM\Column(type: Types::TEXT, length: 75)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $planDescription = "";

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $planTime;

    #[ORM\Column(type: Types::INTEGER, length: 5)]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $planGametime = null;

    #[ORM\Column(enumType: PlanState::class)]
    private PlanState $planState = PlanState::DESIGN;
    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $planLockUserId = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    // @phpstan-ignore-next-line float|null but database expects float
    private ?float $planLastupdate = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'NONE'])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $planPreviousstate = 'NONE';

    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $planActive = 1;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $planConstructionstart = null;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $planType = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $planEnergyError = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $planAltersEnergyDistribution = 0;

    /**
     * @var Collection<int, PlanLayer>
     */
    #[ORM\OneToMany(
        targetEntity: PlanLayer::class,
        mappedBy: 'plan',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $planLayer;

    /**
     * @var Collection<int, PlanDelete>
     */
    #[ORM\OneToMany(targetEntity: PlanDelete::class, mappedBy: 'plan', cascade: ['persist'])]
    private Collection $planDelete;

    /**
     * @var Collection<int, Fishing>
     */
    #[ORM\OneToMany(targetEntity: Fishing::class, mappedBy: 'plan', cascade: ['persist'])]
    private Collection $fishing;

    /**
     * @var Collection<int, PlanMessage>
     */
    #[ORM\OneToMany(
        targetEntity: PlanMessage::class,
        mappedBy: 'plan',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $planMessage;

    /**
     * @var Collection<int, PlanRestrictionArea>
     */
    #[ORM\OneToMany(targetEntity: PlanRestrictionArea::class, mappedBy: 'plan', cascade: ['persist'])]
    private Collection $planRestrictionArea;

    /**
     * @var Collection<int, Grid>
     */
    #[ORM\JoinTable(name: 'grid_removed')]
    #[ORM\JoinColumn(name: 'grid_removed_plan_id', referencedColumnName: 'plan_id')]
    #[ORM\InverseJoinColumn(name: 'grid_removed_grid_persistent', referencedColumnName: 'grid_id')]
    #[ORM\ManyToMany(targetEntity: Grid::class, inversedBy: 'planToRemove', cascade: ['persist'])]
    private Collection $gridToRemove;

    /**
     * @var Collection<int, PlanPolicy>
     */
    #[ORM\OneToMany(
        targetEntity: PlanPolicy::class,
        mappedBy: 'plan',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $planPolicies;

    public function __construct()
    {
        $this->planLayer = new ArrayCollection();
        $this->planDelete = new ArrayCollection();
        $this->fishing = new ArrayCollection();
        $this->planMessage = new ArrayCollection();
        $this->planRestrictionArea = new ArrayCollection();
        $this->gridToRemove = new ArrayCollection();
        $this->planPolicies = new ArrayCollection();
    }

    public function getPlanId(): ?int
    {
        return $this->planId;
    }

    public function setPlanId(?int $planId): Plan
    {
        $this->planId = $planId;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): Plan
    {
        $this->country = $country;
        return $this;
    }

    public function getPlanName(): ?string
    {
        return $this->planName;
    }

    public function setPlanName(?string $planName): Plan
    {
        $this->planName = $planName;
        return $this;
    }

    public function getPlanDescription(): ?string
    {
        return $this->planDescription;
    }

    public function setPlanDescription(?string $planDescription): Plan
    {
        $this->planDescription = $planDescription;
        return $this;
    }

    public function getPlanTime(): \DateTime
    {
        return $this->planTime;
    }

    public function setPlanTime(\DateTime $planTime): Plan
    {
        $this->planTime = $planTime;
        return $this;
    }

    public function getPlanGametime(): ?int
    {
        return $this->planGametime;
    }

    public function setPlanGametime(?int $planGametime): Plan
    {
        $this->planGametime = $planGametime;
        return $this;
    }

    public function getPlanState(): PlanState
    {
        return $this->planState;
    }

    public function setPlanState(PlanState $planState): Plan
    {
        $this->planState = $planState;
        return $this;
    }

    public function getPlanLockUserId(): ?int
    {
        return $this->planLockUserId;
    }

    public function setPlanLockUserId(?int $planLockUserId): Plan
    {
        $this->planLockUserId = $planLockUserId;
        return $this;
    }

    public function getPlanLastupdate(): ?float
    {
        return $this->planLastupdate;
    }

    public function setPlanLastupdate(?float $planLastupdate): Plan
    {
        $this->planLastupdate = $planLastupdate;
        return $this;
    }

    public function getPlanPreviousstate(): ?string
    {
        return $this->planPreviousstate;
    }

    public function setPlanPreviousstate(?string $planPreviousstate): Plan
    {
        $this->planPreviousstate = $planPreviousstate;
        return $this;
    }

    public function getPlanActive(): ?int
    {
        return $this->planActive;
    }

    public function setPlanActive(?int $planActive): Plan
    {
        $this->planActive = $planActive;
        return $this;
    }

    public function getPlanConstructionstart(): ?int
    {
        return $this->planConstructionstart;
    }

    public function setPlanConstructionstart(?int $planConstructionstart): Plan
    {
        $this->planConstructionstart = $planConstructionstart;
        return $this;
    }

    public function getPlanType(): ?int
    {
        return $this->planType;
    }

    public function setPlanType(int|string|null $planType): Plan
    {
        if (is_string($planType)) {
            $planType = self::convertToNewPlanType($planType);
        }
        $this->planType = $planType;
        return $this;
    }

    private static function convertToNewPlanType(string $planType): int
    {
        $newPlanType = (int)$planType; // assuming a correct db value, which is int of bit flags.

        // detect old way of comma separated of 3 bits, order has meaning
        if (1 === preg_match('/(\d),(\d),(\d)/', $planType, $matches)) { // e.g. 0,1,0
            array_shift($matches);
            array_walk($matches, function (string $item, int $key) use (&$newPlanType) {
                if ($item === '0') {
                    return;
                }
                $newPlanType |= array_values(PolicyTypeValue::getConstants())[$key];
            });
            return $newPlanType;
        }

        // detect old way of up to 3 comma separated strings energy/ecology/shipping
        //   but also accept the new "fishing" for ecology, and random order
        if (1 === preg_match(
            '/(energy|ecology|fishing|shipping)'.
                '(?:,(energy|ecology|fishing|shipping)(?:,(energy|ecology|fishing|shipping))?)?/',
            $planType,
            $matches
        )) {
            array_shift($matches);
            array_walk($matches, function (string $item) use (&$newPlanType) {
                if ($item == 'ecology') {
                    $item = 'fishing';
                }
                $newPlanType |= PolicyTypeValue::getConstants()[strtoupper($item)];
            });
            return $newPlanType;
        }

        return $newPlanType;
    }

    public function getPlanEnergyError(): ?int
    {
        return $this->planEnergyError;
    }

    public function setPlanEnergyError(?int $planEnergyError): Plan
    {
        $this->planEnergyError = $planEnergyError;
        return $this;
    }

    public function getPlanAltersEnergyDistribution(): ?int
    {
        return $this->planAltersEnergyDistribution;
    }

    public function setPlanAltersEnergyDistribution(?int $planAltersEnergyDistribution): Plan
    {
        $this->planAltersEnergyDistribution = $planAltersEnergyDistribution;
        return $this;
    }

    /**
     * @return Collection<int, PlanLayer>
     */
    public function getPlanLayer(): Collection
    {
        return $this->planLayer;
    }

    public function addPlanLayer(PlanLayer $planLayer): self
    {
        if (!$this->planLayer->contains($planLayer)) {
            $this->planLayer->add($planLayer);
            $planLayer->setPlan($this);
        }

        return $this;
    }

    public function removePlanLayer(PlanLayer $planLayer): self
    {
        $this->planLayer->removeElement($planLayer);
        // Since orphanRemoval is set, no need to explicitly remove $planLayer from the database
        return $this;
    }

    /**
     * @return Collection<int, PlanDelete>
     */
    public function getPlanDelete(): Collection
    {
        return $this->planDelete;
    }

    public function addPlanDelete(PlanDelete $planDelete): self
    {
        if (!$this->planDelete->contains($planDelete)) {
            $this->planDelete->add($planDelete);
            $planDelete->setPlan($this);
        }

        return $this;
    }

    public function removePlanDelete(PlanDelete $planDelete): self
    {
        $this->planDelete->removeElement($planDelete);
        // Since orphanRemoval is set, no need to explicitly remove $planDelete from the database
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
            $fishing->setPlan($this);
        }

        return $this;
    }

    public function removeFishing(Fishing $fishing): self
    {
        if ($this->fishing->removeElement($fishing)) {
            // set the owning side to null (unless already changed)
            if ($fishing->getPlan() === $this) {
                $fishing->setPlan(null);
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
        $this->planMessage->contains($planMessage);
        // Since orphanRemoval is set, no need to explicitly remove $planMessage from the database
        return $this;
    }

    public function removePlanMessage(PlanMessage $planMessage): self
    {
        if ($this->planMessage->removeElement($planMessage)) {
            // set the owning side to null (unless already changed)
            if ($planMessage->getPlan() === $this) {
                $planMessage->setPlan(null);
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
            $planRestrictionArea->setPlan($this);
        }

        return $this;
    }

    public function removePlanRestrictionArea(PlanRestrictionArea $planRestrictionArea): self
    {
        if ($this->planRestrictionArea->removeElement($planRestrictionArea)) {
            // set the owning side to null (unless already changed)
            if ($planRestrictionArea->getPlan() === $this) {
                $planRestrictionArea->setPlan(null);
            }
        }

        return $this;
    }

    public function getGridToRemove(): Collection
    {
        return $this->gridToRemove;
    }

    public function addGridToRemove(Grid $gridToRemove): self
    {
        if (!$this->gridToRemove->contains($gridToRemove)) {
            $this->gridToRemove->add($gridToRemove);
            $gridToRemove->addPlanToRemove($this);
        }

        return $this;
    }

    public function removeGridToRemove(Grid $planToRemove): self
    {
        if ($this->gridToRemove->removeElement($planToRemove)) {
            $planToRemove->removePlanToRemove($this);
        }

        return $this;
    }

    public function updatePlanConstructionTime(): self
    {
        $highest = 0;
        foreach ($this->getPlanLayer() as $key => $planLayer) {
            foreach ($planLayer->getLayer()->getOriginalLayer()->getLayerStates() as $key2 => $stateByTime) {
                if ($stateByTime["state"] == "ASSEMBLY" && $stateByTime['time'] > $highest) {
                    $highest = $stateByTime['time'];
                    break;
                }
            }
        }
        $this->setPlanLastupdate(microtime(true));
        $this->setPlanConstructionstart($this->getPlanGametime() - $highest);
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
            $planPolicy->setPlan($this);
        }

        return $this;
    }

    public function removePlanPolicy(PlanPolicy $planPolicy): static
    {
        $this->planPolicies->removeElement($planPolicy);
        // Since orphanRemoval is set, no need to explicitly remove $planMessage from the database
        return $this;
    }
}
