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
            'Set a password' => 'local',
            'Set users from MSP Challenge' => 'App\\Domain\\API\\v1\\Auth_MSP' // to do, get this via a function call
        ];
        $userTeams = $builder->getData()->getCountries();
        $externalProviderHelp = 'Enter one username or e-mail address per line, and click Find.';
        $builder
            ->add('passwordAdmin', HiddenType::class)
            ->add('passwordPlayer', HiddenType::class)

            ->add('provider_admin', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('password_admin', TextType::class, [
                'mapped' => false
            ])
            ->add("users_admin", CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'prototype' => true,
                'help' => $externalProviderHelp,
                'required' => false,
                'data' => [],
                'mapped' => false
            ])

            ->add('provider_region', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('password_region', TextType::class, [
                'mapped' => false
            ])
            ->add("users_region", CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'prototype' => true,
                'help' => $externalProviderHelp,
                'required' => false,
                'data' => [],
                'mapped' => false
            ])
            
            ->add('provider_player', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => false, 'multiple' => false
            ])
            ->add('password_playerall', TextType::class, [
                'mapped' => false
            ])
            ->add('users_playerall', CollectionType::class, [
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
                ->add("password_player_country_{$country['country_id']}", TextType::class, ['mapped' => false])
                ->add("users_player_country_{$country['country_id']}", CollectionType::class, [
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
