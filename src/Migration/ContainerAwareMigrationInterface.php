<?php

namespace App\Migration;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface ContainerAwareMigrationInterface
{
    public function setContainer(ContainerInterface $container): void;
}
