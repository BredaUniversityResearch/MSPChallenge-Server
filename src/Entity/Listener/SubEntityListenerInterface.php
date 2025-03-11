<?php

namespace App\Entity\Listener;

interface SubEntityListenerInterface
{
    /**
     * @return string[]
     */
    public function getSupportedEntityClasses(): array;
}
