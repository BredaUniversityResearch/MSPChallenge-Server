<?php

namespace App\Form;

use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameListAddFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entityManager = $options['entity_manager'];
        $builder
            ->add('name')
            ->add('gameConfigVersion', ChoiceType::class, [
                'placeholder' => 'Choose an option by clicking here...',
                'choices' => [$entityManager->getRepository(GameConfigVersion::class)->findAll()],
                'choice_value' => 'id',
                'choice_label' => function (?GameConfigVersion $gameConfigVersion) {
                    return $gameConfigVersion->getGameConfigFile()->getFilename().
                        ' v'.$gameConfigVersion->getVersion().': '.$gameConfigVersion->getVersionMessage();
                },
                'group_by' => function ($choice, $key, $value) {
                    return $choice->getGameConfigFile()->getFilename();
                },
            ])
            ->add('gameGeoServer', ChoiceType::class, [
                'choices' => [$entityManager->getRepository(GameGeoServer::class)->findAll()],
                'choice_value' => 'id',
                'choice_label' => function (?GameGeoServer $gameGeoServer) {
                    return $gameGeoServer->getName();
                },
                'group_by' => function () {
                }
            ])
            ->add('gameWatchdogServer', ChoiceType::class, [
                'choices' => [$entityManager->getRepository(GameWatchdogServer::class)->findAll()],
                'choice_value' => 'id',
                'choice_label' => function (?GameWatchdogServer $gameWatchdogServer) {
                    return $gameWatchdogServer->getName();
                },
                'group_by' => function () {
                }
            ])
            ->add('passwordAdmin', TextType::class, [
                'help' => 'This and more sophisticated user access settings can always be changed after the session 
                has been successfully created.'
            ])
            ->add('passwordPlayer', TextType::class, [
                'help' => 'This and more sophisticated user access settings can always be changed after the session 
                has been successfully created.',
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameList::class,
        ]);

        $resolver->setRequired('entity_manager');
    }
}
