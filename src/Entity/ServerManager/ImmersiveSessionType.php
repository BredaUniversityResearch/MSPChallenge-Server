<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\ImmersiveSessionTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImmersiveSessionTypeRepository::class)]
class ImmersiveSessionType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $dataSchema = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $dataDefault = null;

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

    public function getDataSchema(): mixed
    {
        return $this->dataSchema;
    }

    public function setDataSchema(mixed $dataSchema): static
    {
        $this->dataSchema = $dataSchema;

        return $this;
    }

    public function getDataDefault(): mixed
    {
        return $this->dataDefault;
    }

    public function setDataDefault(mixed $dataDefault): static
    {
        $this->dataDefault = $dataDefault;

        return $this;
    }
}
