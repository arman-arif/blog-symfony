<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final readonly class MaintenanceListener implements EventSubscriberInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private AuthorizationCheckerInterface $authorization,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', -20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->settings->getBool('maintenance.enabled')) {
            return;
        }
        $path = $event->getRequest()->getPathInfo();
        foreach (['/admin', '/login', '/logout', '/_profiler', '/assets', '/build', '/uploads', '/health'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }
        if ($this->authorization->isGranted('admin.access')) {
            return;
        }

        $response = new Response($this->twig->render('front/maintenance.html.twig', [
            'message' => $this->settings->getString('maintenance.message'),
            'site_name' => $this->settings->getString('general.name'),
        ]), Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '3600', 'Cache-Control' => 'no-store']);
        $event->setResponse($response);
    }
}
