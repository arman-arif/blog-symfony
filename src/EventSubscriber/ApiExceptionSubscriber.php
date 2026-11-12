<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Preserve framework logging and security handling; replace only the final API error representation. */
#[AsEventListener(event: ExceptionEvent::class, priority: -64)]
final readonly class ApiExceptionSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !preg_match('#^/(?:api(?:/|$)|admin/api(?:/|$))#', $event->getRequest()->getPathInfo())) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        unset($headers['Content-Type']);
        $event->setResponse(new JsonResponse([
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Request failed',
            'status' => $status,
        ], $status, array_merge($headers, [
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
        ])));
    }
}
