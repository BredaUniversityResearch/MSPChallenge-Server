<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planId;

    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $planCountryId;

    #[ORM\Column(type: Types::STRING, length: 75)]
    private ?string $planName;

    #[ORM\Column(type: Types::TEXT, length: 75)]
    private ?string $planDescription;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?string $planTime;
}
