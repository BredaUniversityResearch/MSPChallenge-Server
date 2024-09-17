<?php

namespace App\Form;

use App\Entity\ServerManager\GameList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameListUserAccessFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $providerChoices = [
            'Set a password' => 'local', 'Set users from an external provider' => 'external'
        ];
        $externalProviders = [
            'MSP Challenge' => 'App\\Domain\\API\\v1\\Auth_MSP' // to do, get this list via a function call
        ];
        $externalProviderHelp = 'Enter one username or e-mail address per line, and click Find.';
        $builder
            ->add('passwordAdmin', HiddenType::class)
            ->add('passwordPlayer', HiddenType::class)
            /*->add('provider_admin', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => true, 'multiple' => false
            ])
            ->add('provider_admin_external', ChoiceType::class, [
                'choices' => $externalProviders, 'mapped' => false
            ])
            ->add('password_admin', TextType::class, [
                'mapped' => false
            ])
            ->add('users_admin', TextType::class, [
                'mapped' => false, 'help' => $externalProviderHelp
            ])
            ->add('provider_region', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => true, 'multiple' => false
            ])
            ->add('provider_region_external', ChoiceType::class, [
                'choices' => $externalProviders, 'mapped' => false
            ])
            ->add('users_region', TextType::class, [
                'mapped' => false, 'help' => $externalProviderHelp
            ])
            ->add('provider_player', ChoiceType::class, [
                'choices' => $providerChoices, 'mapped' => false, 'expanded' => true, 'multiple' => false
            ])
            ->add('provider_player_external', ChoiceType::class, [
                'choices' => $externalProviders, 'mapped' => false
            ])
            ->add('users_region', TextType::class, [
                'mapped' => false, 'help' => $externalProviderHelp
            ])
            ->add('users_playerall', TextType::class, [
                'mapped' => false, 'help' => $externalProviderHelp
            ])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameList::class,
        ]);
    }
}
