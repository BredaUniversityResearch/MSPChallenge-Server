<?php

namespace App\Entity\Listener;

use Doctrine\ORM\Event\PostLoadEventArgs;

interface PostLoadEventListenerInterface
{
    public function postLoad(PostLoadEventArgs $event): void;
}
