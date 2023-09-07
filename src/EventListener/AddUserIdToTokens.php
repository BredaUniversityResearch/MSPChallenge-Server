<?php

namespace App\EventListener;

use App\Domain\API\v1\User;
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
        $user = $event->getUser();
        if ($user instanceof User) {
            $payload['uid'] = $event->getUser()->getUserIdentifier();
        }
        $event->setData($payload);
    }
}
