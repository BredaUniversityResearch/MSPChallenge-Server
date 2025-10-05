<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Repository\ServerManager\ImmersiveSessionDockerApiRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotBlank(message: "The address cannot be empty.")]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'placeholder' => '%env(string:URL_WEB_SERVER_HOST)%'
            ]
        ]
    )]
    private ?string $address = null;

    #[Groups(['read'])]
    #[AppMappings\Property\TableColumn]
    #[ORM\Column]
    #[Assert\NotBlank(message: "The port cannot be empty.")]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'placeholder' => '2375'
            ]
        ]
    )]
    private ?int $port = null;

    #[Groups(['read'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "The scheme cannot be empty.")]
    #[Assert\Choice(choices: ['http', 'https'], message: 'Set a valid scheme, either: http or https.')]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'http'
            ]
        ]
    )]
    private ?string $scheme = null;

    private ?\DateTime $lastDockerEventAt = null;

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

    public function getLastDockerEventAt(): ?\DateTime
    {
        return $this->lastDockerEventAt;
    }

    public function setLastDockerEventAt(?\DateTime $lastDockerEventAt): static
    {
        $this->lastDockerEventAt = $lastDockerEventAt;

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = str_replace('://', '', $this->getScheme());
        $port = $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
