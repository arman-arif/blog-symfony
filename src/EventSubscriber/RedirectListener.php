<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\RedirectRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class RedirectListener
{
    public function __construct(
        private RedirectRepository $redirects,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 32)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !in_array($event->getRequest()->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!$this->shouldCheck($path)) {
            return;
        }

        $redirect = $this->cache->get('redirect.'.sha1($path), function (ItemInterface $item) use ($path): ?array {
            $item->expiresAfter(3600);
            $item->tag(['redirects']);
            $redirect = $this->redirects->findOneBySourcePath($path)
                ?? $this->redirects->findOneBySourcePath(rtrim($path, '/') ?: '/');

            return null === $redirect ? null : [
                'target' => $redirect->getTargetUrl(),
                'status' => $redirect->getStatusCode(),
            ];
        });

        if (null === $redirect || $redirect['target'] === $path) {
            return;
        }

        $target = str_starts_with($redirect['target'], '/') ? $event->getRequest()->getSchemeAndHttpHost().$redirect['target'] : $redirect['target'];
        $this->redirects->incrementHits($path);
        $event->setResponse(new RedirectResponse($target, $redirect['status']));
    }

    private function shouldCheck(string $path): bool
    {
        return !preg_match('#^/(admin|api|login|logout|register|reset-password|verify|2fa|profile|saved|assets|uploads|media|feed|sitemap|robots\.txt|_)#', $path);
    }
}
