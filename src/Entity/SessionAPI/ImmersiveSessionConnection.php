<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiProperty;
use App\Repository\SessionAPI\ImmersiveSessionConnectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ImmersiveSessionConnectionRepository::class)]
class ImmersiveSessionConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'connection', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImmersiveSession $session = null;

    #[Groups(['read'])]
    #[ORM\Column]
    private ?int $dockerApiID = null;

    #[Groups(['read'])]
    #[ORM\Column]
    private ?int $port = null;

    #[Groups(['read'])]
    #[ORM\Column(length: 255)]
    private ?string $dockerContainerID = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?ImmersiveSession
    {
        return $this->session;
    }

    public function setSession(ImmersiveSession $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getDockerApiID(): ?int
    {
        return $this->dockerApiID;
    }

    public function setDockerApiID(int $dockerApiID): static
    {
        $this->dockerApiID = $dockerApiID;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getDockerContainerID(): ?string
    {
        return $this->dockerContainerID;
    }

    public function setDockerContainerID(?string $dockerContainerID): static
    {
        $this->dockerContainerID = $dockerContainerID;

        return $this;
    }
}
