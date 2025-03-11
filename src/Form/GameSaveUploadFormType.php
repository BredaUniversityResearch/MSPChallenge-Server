<?php

namespace App\Form;

use App\Entity\ServerManager\GameSave;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class GameSaveUploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('saveZip', FileType::class, [
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '300M',
                        'mimeTypes' => [
                            'application/zip'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid ZIP archive',
                    ]),
                    new NotBlank()
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameSave::class,
        ]);
    }
}
