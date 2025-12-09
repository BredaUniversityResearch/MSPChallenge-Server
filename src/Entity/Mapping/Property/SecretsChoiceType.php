<?php

namespace App\Entity\Mapping\Property;

use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;

class SecretsChoiceType extends SymfonyFormType\ChoiceType
{
    public function __construct()
    {
        parent::__construct();
    }
}
