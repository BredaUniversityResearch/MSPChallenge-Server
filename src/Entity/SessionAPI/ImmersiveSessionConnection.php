<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Common\EntityEnums\ImmersiveSessionConnectionStatus;
use App\Repository\SessionAPI\ImmersiveSessionConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImmersiveSessionConnectionRepository::class)]
class ImmersiveSessionConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ApiProperty(
        readable: false,
    )]
    #[ORM\OneToOne(inversedBy: 'connection', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImmersiveSession $session = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ImmersiveSessionConnectionStatus::ALL,
            'description' => 'The status of the immersive session connection',
            'example' => ImmersiveSessionConnectionStatus::STARTING->value
        ]
    )]
    #[ORM\Column(enumType: ImmersiveSessionConnectionStatus::class)]
    private ImmersiveSessionConnectionStatus $status = ImmersiveSessionConnectionStatus::STARTING;

    #[ApiProperty(
        openapiContext: [
            'example' => ''
        ]
    )]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $statusResponse = null;

    #[ORM\Column]
    private ?int $dockerApiID = null;

    #[ORM\Column]
    private ?int $port = null;

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
        // set the owning side of the relation if necessary - to prevent looping
        if ($session->getConnection() !== $this) {
            $session->setConnection($this);
        }
        $this->session = $session;
        return $this;
    }

    public function getStatus(): ImmersiveSessionConnectionStatus
    {
        return $this->status;
    }

    public function setStatus(ImmersiveSessionConnectionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusResponse(): mixed
    {
        return $this->statusResponse;
    }

    public function setStatusResponse(mixed $statusResponse): static
    {
        $this->statusResponse = $statusResponse;

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
