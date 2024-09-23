<?php

namespace App\Form;

use App\Entity\ServerManager\GameList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameListUserAccessFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $providerChoices = [
            'Let them log in with a password' => 'local',
            'Let them log in with an MSP Challenge account' => 'App\\Domain\\API\\v1\\Auth_MSP'
            // to do, get this via a function call
        ];
        $userTeams = $builder->getData()->getCountries();
        $externalProviderHelp = 'Enter one username or e-mail address per line, and click Find.';
        $builder
            ->add('passwordAdmin', HiddenType::class)
            ->add('passwordPlayer', HiddenType::class)

            ->add('providerAdmin', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('passwordAdminRaw', TextType::class, [
                'mapped' => false, 'required' => false
            ])
            ->add("usersAdmin", CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'prototype' => true,
                'help' => $externalProviderHelp,
                'required' => false,
                'data' => [],
                'mapped' => false
            ])

            ->add('providerRegion', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('passwordRegionRaw', TextType::class, [
                'mapped' => false, 'required' => false
            ])
            ->add("usersRegion", CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'prototype' => true,
                'help' => $externalProviderHelp,
                'required' => false,
                'data' => [],
                'mapped' => false
            ])
            
            ->add('providerPlayer', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('passwordPlayerall', TextType::class, [
                'mapped' => false, 'required' => false
            ])
            ->add('usersPlayerall', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'prototype' => true,
                'help' => $externalProviderHelp,
                'required' => false,
                'data' => [],
                'mapped' => false
            ]);
        foreach ($userTeams as $key => $country) {
            $builder
                ->add("passwordPlayerCountry{$country['country_id']}", TextType::class, [
                    'mapped' => false, 'required' => false
                ])
                ->add("usersPlayerCountry{$country['country_id']}", CollectionType::class, [
                    'entry_type' => TextType::class,
                    'allow_add' => true,
                    'prototype' => true,
                    'help' => $externalProviderHelp,
                    'required' => false,
                    'data' => [],
                    'mapped' => false
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameList::class,
        ]);
    }
}
