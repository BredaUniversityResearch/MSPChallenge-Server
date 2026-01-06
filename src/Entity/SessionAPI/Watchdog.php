<?php

namespace App\Entity\SessionAPI;

use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Entity\Interface\WatchdogInterface;
use App\Entity\Listener\WatchdogEntityListener;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\Trait\LazyLoadersTrait;
use App\Entity\Trait\WatchdogTrait;
use App\Repository\SessionAPI\WatchdogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WatchdogRepository::class)]
#[ORM\Table(uniqueConstraints: [
    new ORM\UniqueConstraint(
        name: 'uq_server_id_archived',
        columns: ['server_id', 'archived']
    )
])]
// timeAware: set a date of deletion in the future and never worry about cleaning up at expiration time. default false
// hardDelete: to enable hard delete after soft delete has already been done. default true
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Watchdog implements WatchdogInterface
{
    use WatchdogTrait, SoftDeleteableEntity, TimestampableEntity, LazyLoadersTrait;

    public const LAZY_LOADING_PROPERTY_GAME_WATCHDOG_SERVER = 'gameWatchdogServer'; // value does not matter

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $serverId = null;

    #[ORM\Column(enumType: WatchdogStatus::class, options: ['default' => WatchdogStatus::REGISTERED])]
    private WatchdogStatus $status = WatchdogStatus::REGISTERED;

    #[ORM\Column(type: Types::BIGINT)]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $token = null;

    #[ORM\Column(type: Types::BIGINT, options: [
        'generated' => 'ALWAYS',
        'as' => 'if(`deleted_at` is null,0,UNIX_TIMESTAMP(deleted_at))'])
    ]
    private int $archived;

    /**
     * @var Collection<int, Simulation>
     */
    #[ORM\OneToMany(mappedBy: 'watchdog', targetEntity: Simulation::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $simulations;

    public function __construct()
    {
        $this->simulations = new ArrayCollection();
        $this->archived = 0;
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
        return $this->archived !== 0;
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

    public function getGameWatchdogServer(): ?GameWatchdogServer
    {
        // fail-safe: Trigger post load to ensure the serialized watchdog has its lazy loaders set
        //  Eg. after serialization into a message and handled by the message handler.
        WatchdogEntityListener::getInstance()->triggerPostLoad($this);
        if (null !== $ll = $this->getLazyLoader(self::LAZY_LOADING_PROPERTY_GAME_WATCHDOG_SERVER)) {
            return $ll();
        }
        return null;
    }
}
