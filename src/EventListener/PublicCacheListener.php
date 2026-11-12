<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class PublicCacheListener implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -1100]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || !$request->isMethod('GET') || !str_starts_with($route, 'front_') || 200 !== $response->getStatusCode()) {
            return;
        }
        if (null !== $this->tokenStorage->getToken()) {
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');

            return;
        }
        if ($response->headers->has('Set-Cookie') || ($request->hasSession() && $request->getSession()->isStarted())) {
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');

            return;
        }
        $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=300, stale-while-revalidate=60');
        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Cookie');
        }
    }
}
