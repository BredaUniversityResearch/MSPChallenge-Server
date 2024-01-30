<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\PolicyTypeValue;
use App\Repository\PlanRepository;
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
    private Country $country;

    #[ORM\Column(type: Types::STRING, length: 75)]
    private ?string $planName;

    #[ORM\Column(type: Types::TEXT, length: 75)]
    private ?string $planDescription;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?string $planTime;

    #[ORM\Column(type: Types::INTEGER, length: 5)]
    private ?int $planGametime;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'DESIGN'])]
    private ?string $planState = 'DESIGN';

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $planLockUserId;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private ?float $planLastupdate = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'NONE'])]
    private ?string $planPreviousstate = 'NONE';

    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 1])]
    private ?int $planActive = 1;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $planConstructionstart;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 0])]
    private ?int $planType = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $planEnergyError = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $planAltersEnergyDistribution = 0;

    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanLayer::class, cascade: ['persist'])]
    private Collection $planLayer;

    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanDelete::class, cascade: ['persist'])]
    private Collection $planDelete;

    // onetomany on fishing

    // onetomany on planmessage

    // onetomany on planrestrictionarea

    public function __construct()
    {
        $this->planLayer = new ArrayCollection();
        $this->planDelete = new ArrayCollection();
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

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): Plan
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

    public function getPlanTime(): ?string
    {
        return $this->planTime;
    }

    public function setPlanTime(?string $planTime): Plan
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

    public function getPlanState(): ?string
    {
        return $this->planState;
    }

    public function setPlanState(?string $planState): Plan
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
        if ($this->planLayer->removeElement($planLayer)) {
            // set the owning side to null (unless already changed)
            if ($planLayer->getPlan() === $this) {
                $planLayer->setPlan(null);
            }
        }

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
        if ($this->planDelete->removeElement($planDelete)) {
            // set the owning side to null (unless already changed)
            if ($planDelete->getPlan() === $this) {
                $planDelete->setPlan(null);
            }
        }

        return $this;
    }

}
