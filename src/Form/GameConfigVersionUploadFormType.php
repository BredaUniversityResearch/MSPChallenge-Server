<?php

namespace App\Form;

use App\Domain\Config\PendingConfigUploads;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Validator\ValidJson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class GameConfigVersionUploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entityManager = $options['entity_manager'];
        $builder
            // the configuration, and the generic configs (parents) that it needs and the server does not have
            ->add('gameConfigFileActual', FileType::class, [
                'mapped' => false,
                'multiple' => true,
                'constraints' => array_merge(
                    [
                        new All([
                            new File([
                                'maxSize' => '30M'
                            ]),
                            new ValidJson()
                        ]),
                        new Count([
                            'max' => PendingConfigUploads::MAX_FILES,
                            'maxMessage' => 'Upload at most {{ limit }} files: the configuration and its parents.'
                        ]),
                    ],
                    // when an upload is waiting for more files, the files that are missing are all that is needed
                    $options['has_pending_upload'] ? [] : [new NotBlank()]
                ),
            ])
            ->add('gameConfigFile', ChoiceType::class, [
                'choices' => [null, $entityManager->getRepository(GameConfigFile::class)->findAll()],
                'choice_value' => 'id',
                'choice_label' => function (?GameConfigFile $gameConfigFile) {
                    return $gameConfigFile?->getFilename() ?? 'New Configuration File...';
                },
                'group_by' => function ($choice, $key, $value) {
                    if (!is_null($choice)) {
                        return 'Existing configuration files';
                    }
                },
                'required' => false
            ])
            ->add('filename', TextType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Add when uploading a completely new configuration file'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Make sure you enter a name for this new configuration.'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_]+$/',
                        'message' => 'No spaces or special characters here, please.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'mapped' => false,
                'attr' => ['placeholder' => 'Add when uploading a completely new configuration file'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Make sure you enter a description for this new configuration.'
                    ])
                ]
            ])
            ->add('versionMessage', TextareaType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit'])
        ;
    }

    public function onPreSubmit(PreSubmitEvent $event): void
    {
        // all of this is purely to pass validation of the unmapped fields
        $formDataArray = $event->getData();
        $em = $event->getForm()->getConfig()->getOptions()['entity_manager'];
        if (!empty($formDataArray['gameConfigFile'])) {
            $gameConfigFile = $em->getRepository(GameConfigFile::class)->find($formDataArray['gameConfigFile']);
            $formDataArray['filename'] = $gameConfigFile->getFilename();
            $formDataArray['description'] = $gameConfigFile->getDescription();
        }
        $event->setData($formDataArray);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameConfigVersion::class,
        ]);

        $resolver->setRequired('entity_manager');
        // an upload that is not complete is waiting for more files
        $resolver->setDefault('has_pending_upload', false);
        $resolver->setAllowedTypes('has_pending_upload', 'bool');
    }
}
