<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Repository\WatchdogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WatchdogRepository::class)]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_server_id_archived', columns: ['server_id', 'archived'])
])]
class Watchdog
{
    use SoftDeleteableEntity, TimestampableEntity;

    private const INTERNAL_SERVER_ID = '019373cc-aa68-7d95-882f-9248ea338014';

    public static function getInternalServerId(): Uuid
    {
        return Uuid::fromString(self::INTERNAL_SERVER_ID);
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $serverId = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(options: ['default' => 80])]
    private int $port = 80;

    #[ORM\Column(length: 255, options: ['default' => 'http'])]
    private string $scheme = 'http';

    #[ORM\Column(enumType: WatchdogStatus::class, options: ['default' => WatchdogStatus::REGISTERED])]
    private ?WatchdogStatus $status = WatchdogStatus::REGISTERED;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $token = null;

    #[ORM\Column(type: 'boolean', options: ['generated' => 'ALWAYS', 'as' => 'IF(deleted_at IS NULL, 0, 1)'])]
    private bool $archived;

    /**
     * @var Collection<int, Simulation>
     */
    #[ORM\OneToMany(mappedBy: 'watchdog', targetEntity: Simulation::class, orphanRemoval: true)]
    private Collection $simulations;

    public function __construct()
    {
        $this->simulations = new ArrayCollection();
        $this->archived = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getServerId(): ?Uuid
    {
        return $this->serverId;
    }

    public function setServerId(Uuid $serverId): static
    {
        $this->serverId = $serverId;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function getStatus(): WatchdogStatus
    {
        return $this->status;
    }

    public function setStatus(WatchdogStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getToken(): ?int
    {
        return $this->token;
    }

    public function setToken(int $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    /**
     * @return Collection<int, Simulation>
     */
    public function getSimulations(): Collection
    {
        return $this->simulations;
    }

    public function addSimulation(Simulation $simulation): static
    {
        if (!$this->simulations->contains($simulation)) {
            $this->simulations->add($simulation);
            $simulation->setWatchdog($this);
        }

        return $this;
    }

    public function removeSimulation(Simulation $simulation): static
    {
        if ($this->simulations->removeElement($simulation)) {
            // set the owning side to null (unless already changed)
            if ($simulation->getWatchdog() === $this) {
                $simulation->setWatchdog(null);
            }
        }

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = $this->getScheme();
        $port = ($this->getServerId() == self::INTERNAL_SERVER_ID ? $_ENV['WATCHDOG_PORT'] : null) ?? $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
