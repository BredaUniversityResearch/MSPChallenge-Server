<?php

namespace App\Form;

use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;
use App\Domain\Common\EntityEnums\ImmersiveSessionTypeID;
use App\Domain\Helper\Util;
use App\Entity\Mapping as AppMappings;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class DynamicEntityFormType extends AbstractType
{
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
                if ($formFieldType == ChoiceType::class &&
                    (null !== $attribute = Util::getPropertyAttribute($property, ORM\Column::class)) &&
                    /** @var ORM\Column $attribute */
                    null !== $attribute->enumType) {
                    // setup choice form type to use enum values - if the property is an enum
                    $formFieldTypeOptions['choices'] ??= $attribute->enumType::cases();
                    $formFieldTypeOptions['choice_label'] ??=
                        fn($enum) => in_array(GetAttributesTrait::class, class_uses($enum)) ?
                            $enum::getDescription($enum) :$enum->name; // Display the name
                    $formFieldTypeOptions['choice_value'] ??=
                        fn($enum) => $enum?->value; // Use the value
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
            // json_document type handling
            if ((null !== $attribute = Util::getPropertyAttribute($property, ORM\Column::class)) &&
                $attribute->type == 'json_document'
            ) {
                $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($property) {
                    $data = $event->getData();
                    $propertyValue = $property->getValue($data);
                    if ($propertyValue === null) {
                        return;
                    }
                    $property->setValue($data, json_encode($propertyValue, JSON_PRETTY_PRINT));
                    $event->setData($data);
                });
                $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($property) {
                    $data = $event->getData();
                    $propertyValue = $property->getValue($data);
                    if ($propertyValue === null) {
                        return;
                    }
                    $property->setValue($data, json_decode($propertyValue, true));
                    $event->setData($data);
                });
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
            'string' => TextType::class,
            'int' => IntegerType::class,
            'bool' => CheckboxType::class,
            default => null, // Skip unsupported types
        };
    }
}
