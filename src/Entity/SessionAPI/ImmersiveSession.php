<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Domain\Common\EntityEnums\ImmersiveSessionTypeID;
use App\src\Repository\SessionAPI\ImmersiveSessionRepository;
use App\Validator\ImmersiveSessionTypeJsonSchema;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImmersiveSessionRepository::class)]
#[ApiResource]
class ImmersiveSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "The name field should not be blank.")]
    #[Assert\NotNull(message: "The name field is required.")]
    private ?string $name = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ImmersiveSessionTypeID::ALL,
            'description' => 'The type of the immersive session',
            'example' => ImmersiveSessionTypeID::ALL[0]
        ]
    )]
    #[ORM\Column(enumType: ImmersiveSessionTypeID::class)]
    #[Assert\NotBlank(message: "The type field should not be blank.")]
    #[Assert\NotNull(message: "The type field is required.")]
    private ImmersiveSessionTypeID $type = ImmersiveSessionTypeID::MIXED_REALITY;

    #[ORM\Column(options: ['default' => -1])]
    #[Assert\NotBlank(message: "The month field should not be blank.")]
    #[Assert\Range(
        notInRangeMessage: "The month must be an integer greater than or equal to -1.",
        min: -1
    )]
    private int $month = -1;

    #[ImmersiveSessionTypeJsonSchema]
    #[ApiProperty(
        openapiContext: [
            'example' => '{
                "key": "value"
            }'
        ]
    )]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $data = null;

    #[ApiProperty(
        openapiContext: [
            'example' => '/api/immersive_session_regions/{regionId}',
        ]
    )]
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: "The region field should not be blank.")]
    #[Assert\NotNull(message: "The region field is required.")]
    private ?ImmersiveSessionRegion $region = null;

    #[ApiProperty(
        writable: false
    )]
    #[ORM\OneToOne(mappedBy: 'session', cascade: ['persist', 'remove'])]
    private ?ImmersiveSessionConnection $connection = null;

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

    public function getType(): ImmersiveSessionTypeID
    {
        return $this->type;
    }

    public function setType(ImmersiveSessionTypeID $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;

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

    public function getRegion(): ?ImmersiveSessionRegion
    {
        return $this->region;
    }

    public function setRegion(ImmersiveSessionRegion $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getConnection(): ?ImmersiveSessionConnection
    {
        return $this->connection;
    }

    public function setConnection(ImmersiveSessionConnection $connection): static
    {
        // set the owning side of the relation if necessary
        if ($connection->getSession() !== $this) {
            $connection->setSession($this);
        }

        $this->connection = $connection;

        return $this;
    }
}
