<?php

namespace App\src\Entity\SessionAPI;

use App\src\Repository\SessionAPI\UserRepository;
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
    #[ORM\JoinColumn(name: 'user_country_id', referencedColumnName: 'countryId', nullable: false)]
    private ?Country $userCountry = null;

    #[ORM\Column(length: 1, nullable: true, options: ['default' => 0])]
    private ?int $userLoggedoff = null;

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
}
