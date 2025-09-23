<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Domain\Common\EntityEnums\ImmersiveSessionStatus;
use App\Domain\Common\EntityEnums\ImmersiveSessionTypeID;
use App\Repository\SessionAPI\ImmersiveSessionRepository;
use App\State\ImmersiveSessionProcessor;
use App\Validator\ImmersiveSessionTypeJsonSchema;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImmersiveSessionRepository::class)]
#[ApiResource(
    processor: ImmersiveSessionProcessor::class,
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class ImmersiveSession
{
    use SoftDeleteableEntity, TimestampableEntity;

    #[Groups(['read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['read', 'write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "The name field should not be blank.")]
    #[Assert\NotNull(message: "The name field is required.")]
    private ?string $name = null;

    #[Groups(['read', 'write'])]
    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'enum' => ImmersiveSessionTypeID::ALL,
            'description' => 'The type of the immersive session',
            'example' => ImmersiveSessionTypeID::MR->value
        ]
    )]
    #[ORM\Column(enumType: ImmersiveSessionTypeID::class)]
    #[Assert\NotBlank(message: "The type field should not be blank.")]
    #[Assert\NotNull(message: "The type field is required.")]
    private ImmersiveSessionTypeID $type = ImmersiveSessionTypeID::MR;

    #[Groups(['read', 'write'])]
    #[ORM\Column(options: ['default' => -1])]
    #[Assert\NotBlank(message: "The month field should not be blank.")]
    #[Assert\Range(
        notInRangeMessage: "The month must be an integer greater than or equal to -1.",
        min: -1
    )]
    private int $month = -1;

    #[Groups(['read'])]
    #[ApiProperty(
        writable: false,
        openapiContext: [
            'type' => 'string',
            'enum' => ImmersiveSessionStatus::ALL,
            'description' => 'The status of the immersive session connection',
            'example' => ImmersiveSessionStatus::STARTING->value
        ]
    )]
    #[ORM\Column(enumType: ImmersiveSessionStatus::class)]
    private ImmersiveSessionStatus $status = ImmersiveSessionStatus::STARTING;

    #[Groups(['read'])]
    #[ApiProperty(
        writable: false,
        openapiContext: [
            'example' => ''
        ]
    )]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $statusResponse = null;

    #[Groups(['read', 'write'])]
    #[ORM\Column]
    private ?float $bottomLeftX = null;

    #[Groups(['read', 'write'])]
    #[ORM\Column]
    private ?float $bottomLeftY = null;

    #[Groups(['read', 'write'])]
    #[ORM\Column]
    private ?float $topRightX = null;

    #[Groups(['read', 'write'])]
    #[ORM\Column]
    private ?float $topRightY = null;

    #[Groups(['read', 'write'])]
    #[ImmersiveSessionTypeJsonSchema]
    #[ApiProperty(
        openapiContext: [
            'example' => [
                'key' => 'value'
            ]
        ]
    )]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $data = null;

    #[Groups(['read'])]
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

    public function getStatus(): ImmersiveSessionStatus
    {
        return $this->status;
    }

    public function setStatus(ImmersiveSessionStatus $status): static
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

    public function getBottomLeftX(): ?float
    {
        return $this->bottomLeftX;
    }

    public function setBottomLeftX(float $bottomLeftX): static
    {
        $this->bottomLeftX = $bottomLeftX;

        return $this;
    }

    public function getBottomLeftY(): ?float
    {
        return $this->bottomLeftY;
    }

    public function setBottomLeftY(float $bottomLeftY): static
    {
        $this->bottomLeftY = $bottomLeftY;

        return $this;
    }

    public function getTopRightX(): ?float
    {
        return $this->topRightX;
    }

    public function setTopRightX(float $topRightX): static
    {
        $this->topRightX = $topRightX;

        return $this;
    }

    public function getTopRightY(): ?float
    {
        return $this->topRightY;
    }

    public function setTopRightY(float $topRightY): static
    {
        $this->topRightY = $topRightY;

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

    public function getConnection(): ?ImmersiveSessionConnection
    {
        return $this->connection;
    }

    public function setConnection(?ImmersiveSessionConnection $connection): static
    {
        $this->connection = $connection;
        if (null == $connection) {
            return $this;
        }
        // set the owning side of the relation if necessary
        if ($connection->getSession() !== $this) {
            $connection->setSession($this);
        }
        return $this;
    }
}
