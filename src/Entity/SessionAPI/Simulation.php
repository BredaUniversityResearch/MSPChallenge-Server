<?php

namespace App\Entity\SessionAPI;

use App\Repository\SessionAPI\SimulationRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: SimulationRepository::class)]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_watchdog_id_name', columns: ['watchdog_id', 'name'])
])]
class Simulation
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'simulations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Watchdog $watchdog = null;

    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(options: ['default' => -2])]
    private int $lastMonth = -2;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getWatchdog(): ?Watchdog
    {
        return $this->watchdog;
    }

    public function setWatchdog(?Watchdog $watchdog): static
    {
        $this->watchdog = $watchdog;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getLastMonth(): int
    {
        return $this->lastMonth;
    }

    public function setLastMonth(int $lastMonth): static
    {
        $this->lastMonth = $lastMonth;

        return $this;
    }
}
