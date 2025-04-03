<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Domain\Common\EntityEnums\ViewingSessionState;
use App\Repository\ViewingSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewingSessionRepository::class)]
#[ApiResource]
class ViewingSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(enumType: ViewingSessionState::class)]
    private ?ViewingSessionState $state = null;

    #[ORM\ManyToOne(inversedBy: 'viewingSessions')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getState(): ?ViewingSessionState
    {
        return $this->state;
    }

    public function setState(ViewingSessionState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
