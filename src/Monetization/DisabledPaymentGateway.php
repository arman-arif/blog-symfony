<?php

declare(strict_types=1);

namespace App\Monetization;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/** Disabled gateway stub: the monetization core ships without credentials or network calls. */
final readonly class DisabledPaymentGateway implements PaymentGatewayInterface
{
    public function createCheckout(int $planId, User $user, string $successUrl, string $cancelUrl): string
    {
        throw new \LogicException('Payments are not configured. Implement PaymentGatewayInterface to enable them.');
    }

    public function handleWebhook(Request $request): void
    {
        throw new \LogicException('Payments are not configured.');
    }
}
