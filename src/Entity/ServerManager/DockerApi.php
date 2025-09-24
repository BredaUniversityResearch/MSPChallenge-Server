<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Repository\ServerManager\ImmersiveSessionDockerApiRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[AppMappings\Plurals('Docker API', 'Docker APIs')]
#[ORM\Entity(repositoryClass: ImmersiveSessionDockerApiRepository::class)]
class DockerApi extends EntityBase
{
    #[Groups(['read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['read'])]
    #[AppMappings\Property\TableColumn]
    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[Groups(['read'])]
    #[AppMappings\Property\TableColumn]
    #[ORM\Column]
    private ?int $port = null;

    #[Groups(['read'])]
    #[ORM\Column(length: 255)]
    private ?string $scheme = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = str_replace('://', '', $this->getScheme());
        $port = $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
