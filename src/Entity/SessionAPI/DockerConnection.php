<?php

namespace App\Entity\SessionAPI;

use App\Entity\Listener\ImmersiveSessionConnectionEntityListener;
use App\Entity\ServerManager\DockerApi;
use App\Entity\Trait\LazyLoadersTrait;
use App\Repository\SessionAPI\DockerConnectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DockerConnectionRepository::class)]
class DockerConnection
{
    public const LAZY_LOADING_PROPERTY_DOCKER_API = 'dockerApi'; // value does not matter

    use LazyLoadersTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $dockerApiID = null;

    #[Groups(['read'])]
    #[ORM\Column]
    private ?int $port = null;

    #[Groups(['read'])]
    #[ORM\Column(length: 255)]
    private ?string $dockerContainerID = null;

    private bool $verified = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    #[Groups(['read'])]
    public function getDockerApi(): ?DockerApi
    {
        // fail-safe: Trigger post load to ensure the serialized connection has its lazy loaders set
        //  Eg. after serialization into a message and handled by the message handler.
        ImmersiveSessionConnectionEntityListener::getInstance()->triggerPostLoad($this);
        if (null !== $ll = $this->getLazyLoader(self::LAZY_LOADING_PROPERTY_DOCKER_API)) {
            return $ll();
        }
        return null;
    }
}
