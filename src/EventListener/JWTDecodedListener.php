<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;

class JWTDecodedListener
{
    /**
     * Modify the decoded JWT payload to use email as the username identifier
     */
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        // If email exists in payload, use it as the username identifier
        // This ensures the UserProvider can load the user by email
        if (isset($payload['email'])) {
            $payload['username'] = $payload['email'];
            $event->setPayload($payload);
        }
    }
}
