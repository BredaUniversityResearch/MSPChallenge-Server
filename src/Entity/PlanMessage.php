<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class PlanMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planMessageId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planMessage')]
    #[ORM\JoinColumn(name: 'plan_message_plan_id', referencedColumnName: 'plan_id', onDelete: 'CASCADE')]
    private ?Plan $plan;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'planMessage')]
    #[ORM\JoinColumn(name: 'plan_message_country_id', referencedColumnName: 'country_id')]
    private ?Country $country;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $planMessageUserName;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $planMessageText;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $planMessageTime;

    public function getPlanMessageId(): ?int
    {
        return $this->planMessageId;
    }

    public function setPlanMessageId(?int $planMessageId): PlanMessage
    {
        $this->planMessageId = $planMessageId;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): PlanMessage
    {
        $this->plan = $plan;
        $plan->addPlanMessage($this);
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): PlanMessage
    {
        $this->country = $country;
        return $this;
    }

    public function getPlanMessageUserName(): ?string
    {
        return $this->planMessageUserName;
    }

    public function setPlanMessageUserName(?string $planMessageUserName): PlanMessage
    {
        $this->planMessageUserName = $planMessageUserName;
        return $this;
    }

    public function getPlanMessageText(): ?string
    {
        return $this->planMessageText;
    }

    public function setPlanMessageText(?string $planMessageText): PlanMessage
    {
        $this->planMessageText = $planMessageText;
        return $this;
    }

    public function getPlanMessageTime(): ?float
    {
        return $this->planMessageTime;
    }

    public function setPlanMessageTime(?float $planMessageTime): PlanMessage
    {
        $this->planMessageTime = $planMessageTime;
        return $this;
    }
}
