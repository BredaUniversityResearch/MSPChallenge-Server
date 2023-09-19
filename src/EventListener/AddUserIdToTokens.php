<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class AddUserIdToTokens
{

    /**
     * @param JWTCreatedEvent $event
     *
     * @return void
     */
    public function __invoke(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $payload['uid'] = $event->getUser()->getUserIdentifier();
        $event->setData($payload);
    }
}
