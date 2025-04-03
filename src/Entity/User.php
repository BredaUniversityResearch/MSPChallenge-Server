<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(length: 11)]
    private ?int $userId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column]
    private ?float $userLastupdate = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $userCountry = null;

    #[ORM\Column(length: 1, nullable: true, options: ['default' => 0])]
    private ?int $userLoggedoff = null;

    /**
     * @var Collection<int, ViewingSession>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ViewingSession::class)]
    private Collection $viewingSessions;

    public function __construct()
    {
        $this->viewingSessions = new ArrayCollection();
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function getUserLastupdate(): ?float
    {
        return $this->userLastupdate;
    }

    public function setUserLastupdate(float $userLastupdate): static
    {
        $this->userLastupdate = $userLastupdate;

        return $this;
    }

    public function getUserCountry(): ?Country
    {
        return $this->userCountry;
    }

    public function setUserCountry(?Country $userCountry): static
    {
        $this->userCountry = $userCountry;

        return $this;
    }

    public function isLoggedOff(): ?int
    {
        return $this->userLoggedoff;
    }

    public function setUserLoggedoff(int $userLoggedoff): static
    {
        $this->userLoggedoff = $userLoggedoff;

        return $this;
    }

    /**
     * @return Collection<int, ViewingSession>
     */
    public function getViewingSessions(): Collection
    {
        return $this->viewingSessions;
    }

    public function addViewingSession(ViewingSession $viewingSession): static
    {
        if (!$this->viewingSessions->contains($viewingSession)) {
            $this->viewingSessions->add($viewingSession);
            $viewingSession->setUser($this);
        }

        return $this;
    }

    public function removeViewingSession(ViewingSession $viewingSession): static
    {
        if ($this->viewingSessions->removeElement($viewingSession)) {
            // set the owning side to null (unless already changed)
            if ($viewingSession->getUser() === $this) {
                $viewingSession->setUser(null);
            }
        }

        return $this;
    }
}
