<?php

namespace App\Entity\Listener;

use Doctrine\ORM\Event\PrePersistEventArgs;

interface PrePersistEventListenerInterface
{
    public function prePersist(PrePersistEventArgs $event): void;
}
