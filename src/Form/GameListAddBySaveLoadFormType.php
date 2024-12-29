<?php

namespace App\Form;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\GameListAndSaveSerializer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameWatchdogServer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameListAddBySaveLoadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entityManager = $options['entity_manager'];
        $saveId = $options['save'];
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $builder
            ->add('name', TextType::class, [
                'data' => $gameSave->getName().' (reloaded)'
            ])
            ->add('gameWatchdogServer', ChoiceType::class, [
                'choices' => [$entityManager->getRepository(GameWatchdogServer::class)->findAll()],
                'choice_value' => 'id',
                'choice_label' => 'name',
                'group_by' => function () {
                }
            ])
            ->add('gameSave', ChoiceType::class, [
                'choices' => [$entityManager->getRepository(GameSave::class)->findBy(['saveVisibility' => 'active'])],
                'choice_value' => 'id',
                'choice_label' => 'name',
                'group_by' => function () {
                },
                'data' => $entityManager->getRepository(GameSave::class)->find($saveId),
                'expanded' => false,
                'multiple' => false
            ])
            ->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit'])
        ;
    }

    public function onSubmit(SubmitEvent $event): void
    {
        $em = $event->getForm()->getConfig()->getOptions()['entity_manager'];
        $submittedGameSession = $event->getData();
        $gameSave = $submittedGameSession->getGameSave();
        $serializer = new GameListAndSaveSerializer($em);
        $newGameSessionFromSave = $serializer->createGameListFromData(
            $serializer->createDataFromGameSave($gameSave)
        );
        $newGameSessionFromSave->setName($submittedGameSession->getName());
        $newGameSessionFromSave->setGameSave($gameSave);
        $newGameSessionFromSave->setGameWatchdogServer($submittedGameSession->getGameWatchdogServer());
        $newGameSessionFromSave->setSessionState(new GameSessionStateValue('request'));
        $event->setData($newGameSessionFromSave);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameList::class,
        ]);

        $resolver->setRequired('entity_manager');
        $resolver->setRequired('save');
    }
}
