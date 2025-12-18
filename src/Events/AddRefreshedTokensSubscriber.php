<?php
// src/EventSubscriber/AddRefreshedTokensSubscriber.php

namespace App\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class AddRefreshedTokensSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $newTokens = $request->attributes->get('_new_tokens');

        if (!$newTokens) {
            return;
        }

        $response = $event->getResponse();
        
        // Ajouter les nouveaux tokens dans les headers HTTP
        $response->headers->set('X-New-Token', $newTokens['token']);
        $response->headers->set('X-New-Refresh-Token', $newTokens['refreshToken']);
        $response->headers->set('X-Token-Refreshed', 'true');
        
        // Optionnel: ajouter le rôle et l'email
        if (isset($newTokens['role'])) {
            $response->headers->set('X-User-Role', $newTokens['role']);
        }
        
        if (isset($newTokens['email'])) {
            $response->headers->set('X-User-Email', $newTokens['email']);
        }

        // Permettre au client de lire ces headers (CORS)
        $response->headers->set('Access-Control-Expose-Headers', 
            'X-New-Token, X-New-Refresh-Token, X-Token-Refreshed, X-User-Role, X-User-Email'
        );
        
        $this->logger->info('Refreshed tokens added to response headers', [
            'email' => $newTokens['email'] ?? 'unknown',
        ]);
    }
}