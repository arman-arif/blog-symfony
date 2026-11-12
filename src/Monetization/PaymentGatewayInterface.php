<?php

declare(strict_types=1);

namespace App\Monetization;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/** Extension point for a future payment provider; no credentials or gateway are shipped. */
interface PaymentGatewayInterface
{
    public function createCheckout(int $planId, User $user, string $successUrl, string $cancelUrl): string;

    public function handleWebhook(Request $request): void;
}
