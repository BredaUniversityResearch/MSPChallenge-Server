<?php

namespace App\Form;

use App\Entity\ServerManager\GameWatchdogServer;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UuidType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GameWatchdogServerFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $placeholder = <<<"JSON"
        {
          "simulation_type": "External",
          "kpis": [
            {
              "categoryName": "...kpi category here...",
              "unit": "...kpi unit here...",
              "valueDefinitions": [
                {
                  "valueName": "...kpi name here..."
                }
              ]
            }
          ]
        }
        JSON;
        $builder
            ->add('name', TextType::class)
            ->add('serverId', UuidType::class)
            ->add('scheme', TextType::class)
            ->add('address', TextType::class)
            ->add('port', TextType::class)
            ->add('simulationSettings', TextareaType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 10,
                    'placeholder' => $placeholder,
                    'title' => "example:\n".$placeholder,
                ],
                'required' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameWatchdogServer::class,
        ]);
    }
}
