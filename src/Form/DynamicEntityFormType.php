<?php

namespace App\Form;

use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;
use App\Domain\Helper\Util;
use App\Entity\Mapping as AppMappings;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DynamicEntityFormType extends AbstractType
{
    public function __construct(private readonly AbstractVault $vault)
    {
    }


    /**
     * @throws ReflectionException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entity = $options['data_class'];
        $reflectionClass = new ReflectionClass($entity);
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            $propertyType = $property->getType();
            $formFieldType = null;
            $formFieldTypeOptions = [];
            if (null !== Util::getPropertyAttribute($property, ORM\Id::class)) {
                continue; // Skip ID properties
            }
            $formFieldTypeLabel = null;
            if (null !== $attribute =
                Util::getPropertyAttribute($property, AppMappings\Property\TableColumn::class)) {
                /** @var AppMappings\Property\TableColumn $attribute */
                if ($attribute->toggleable) {
                    continue; // Skip action properties
                }
                // set the name of the field to the label of the column if it is not set
                $formFieldTypeLabel = $attribute->label;
            }
            if (null !== $attribute =
                Util::getPropertyAttribute($property, AppMappings\Property\FormFieldType::class)) {
                /** @var AppMappings\Property\FormFieldType $attribute */
                $formFieldType = $attribute->type;
                $formFieldTypeOptions = $attribute->options;
                if ($formFieldTypeLabel) {
                    $formFieldTypeOptions['label'] ??= $formFieldTypeLabel;
                }
                if (is_a($formFieldType, SymfonyFormType\ChoiceType::class, true)) {
                    // Setup choice form type to use enum values - if the property is an enum
                    if ((null !== $attribute = Util::getPropertyAttribute($property, ORM\Column::class)) &&
                        /** @var ORM\Column $attribute */
                        null !== $attribute->enumType) {
                        $formFieldTypeOptions['choices'] ??= $attribute->enumType::cases();
                        $formFieldTypeOptions['choice_label'] ??=
                            fn($enum) => in_array(GetAttributesTrait::class, class_uses($enum)) ?
                                $enum::getDescription($enum) : $enum->name; // Display the name
                        $formFieldTypeOptions['choice_value'] ??= fn($enum) => $enum?->value; // Use the value
                    // Setup choice form type to refer to secrets
                    } elseif (is_a($formFieldType, AppMappings\Property\SecretsChoiceType::class, true)) {
                        $formFieldTypeOptions['label'] ??= 'Secret to use for '.$propertyName;
                        $formFieldTypeOptions['choices'] ??= array_keys($this->vault->list());
                        $formFieldTypeOptions['choice_label'] ??= fn($value) => $value; // Use the value
                    }
                }
            }
            /** @var ?\ReflectionNamedType $propertyType */
            $formFieldType ??= $this->defaultTypeToFormFieldTypeMapping($propertyType?->getName());
            if (null === $formFieldType) {
                continue;
            }
            if (null !== ($formFieldTypeOptions['attr']['placeholder'] ?? null)) {
                $formFieldTypeOptions['attr']['title'] = "example:\n".$formFieldTypeOptions['attr']['placeholder'];
            }
            $builder->add($propertyName, $formFieldType, $formFieldTypeOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('data_class'); // Make 'data_class' a required option
    }

    private function defaultTypeToFormFieldTypeMapping(?string $type): ?string
    {
        return match ($type) {
            'string' => SymfonyFormType\TextType::class,
            'int' => SymfonyFormType\IntegerType::class,
            'bool' => SymfonyFormType\CheckboxType::class,
            default => null, // Skip unsupported types
        };
    }
}
