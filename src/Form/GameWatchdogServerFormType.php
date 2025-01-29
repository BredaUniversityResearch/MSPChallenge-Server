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
              "categoryColor": "{$this->generateRandomHexColor()}",
              "unit": "hour",
              "valueDefinitions": [
                {
                  "valueName": "...kpi name here...",
                  "valueColor": "{$this->generateRandomHexColor()}"
                }
              ]
            }
          ]
        }
        JSON;
        $builder
            ->add('name', TextType::class)
            ->add('server_id', UuidType::class)
            ->add('scheme', TextType::class)
            ->add('address', TextType::class)
            ->add('port', TextType::class)
            ->add('simulation_settings', TextareaType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 10,
                    'placeholder' => $placeholder,
                    'title' => "example:\n".$placeholder,
                ],
                'required' => true
            ]);
    }

    private function generateRandomHexColor(): string
    {
        $red = dechex(rand(0, 255));
        $green = dechex(rand(0, 255));
        $blue = dechex(rand(0, 255));

        // Ensure each component is two characters long
        $red = str_pad($red, 2, '0', STR_PAD_LEFT);
        $green = str_pad($green, 2, '0', STR_PAD_LEFT);
        $blue = str_pad($blue, 2, '0', STR_PAD_LEFT);

        return "#{$red}{$green}{$blue}";
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GameWatchdogServer::class,
        ]);
    }
}
