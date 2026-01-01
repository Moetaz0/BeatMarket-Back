<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListener
{
    /**
     * Add custom data to the JWT payload
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $payload = $event->getData();

        // Add user email (identifier) to payload for proper user loading
        $payload['email'] = $user->getUserIdentifier();

        // Optionally add user ID for additional reference
        if (method_exists($user, 'getId')) {
            $payload['id'] = $user->getId();
        }

        $event->setData($payload);
    }
}
