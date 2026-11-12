<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersListener implements EventSubscriberInterface
{
    private const string NONCE_ATTRIBUTE = '_csp_nonce';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 512],
            KernelEvents::RESPONSE => ['onResponse', -512],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::NONCE_ATTRIBUTE, rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $nonce = (string) $request->attributes->get(self::NONCE_ATTRIBUTE, '');
        $csp = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; ".
            "script-src 'self' 'nonce-".$nonce."' https://www.googletagmanager.com; ".
            "style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; ".
            "connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com; ".
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; ".
            "media-src 'self'";
        if ($request->isSecure()) {
            $csp .= '; upgrade-insecure-requests';
        }
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
