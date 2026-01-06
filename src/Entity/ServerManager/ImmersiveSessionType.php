<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\ImmersiveSessionTypeID;
use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Repository\ServerManager\ImmersiveSessionTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;
use Symfony\Component\Validator\Constraints as Assert;

#[AppMappings\Plurals('Immersive session type', 'Immersive session types')]
#[ORM\Entity(repositoryClass: ImmersiveSessionTypeRepository::class)]
class ImmersiveSessionType extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true, enumType: ImmersiveSessionTypeID::class)]
    #[AppMappings\Property\FormFieldType(type: SymfonyFormType\ChoiceType::class)]
    private ImmersiveSessionTypeID $type = ImmersiveSessionTypeID::MR;

    #[AppMappings\Property\TableColumn(label: "Name")]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "The name field should not be blank.")]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextareaType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'rows' => 10
            ]
        ]
    )]
    private mixed $dataSchema = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextareaType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'rows' => 10
            ]
        ]
    )]
    private mixed $dataDefault = null;

    public function getId(): ?int
    {
        return $this->id;
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
