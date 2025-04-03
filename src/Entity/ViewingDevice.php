<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Domain\Common\EntityEnums\ViewingDeviceType;
use App\Repository\ViewingDeviceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewingDeviceRepository::class)]
#[ApiResource]
class ViewingDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ViewingDeviceType::class)]
    private ?ViewingDeviceType $type = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $dataSchema = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $data = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?ViewingDeviceType
    {
        return $this->type;
    }

    public function setType(ViewingDeviceType $type): static
    {
        $this->type = $type;

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

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): static
    {
        $this->data = $data;

        return $this;
    }
}
