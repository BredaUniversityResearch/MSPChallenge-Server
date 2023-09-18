<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class AccessTokenAuthorizationListener
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }


    /**
     * @param JWTDecodedEvent $event
     *
     * @return void
     */
    public function __invoke(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $request = $this->requestStack->getCurrentRequest();
        // @todo this should be refactored once we have a class stipulating installed simulations
        $looserAPI = [
            '/\/api\/Simulations\//',
            '/\/api\/MEL\//',
            '/\/api\/SEL\//',
            '/\/api\/CEL\//'
        ];
        $strictnessRequired = true;
        foreach ($looserAPI as $looserAPIClass) {
            if (preg_match($looserAPIClass, $request->getPathInfo())) {
                $strictnessRequired = false;
            }
        }
        if ($strictnessRequired && empty($payload['uid'])) {
            // this is the wrong JWT for the API endpoint being requested
            $event->markAsInvalid();
        }
    }
}
