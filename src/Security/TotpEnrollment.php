<?php

declare(strict_types=1);

namespace App\Security;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

final readonly class TotpEnrollment implements TwoFactorInterface
{
    public function __construct(private string $secret, private string $username)
    {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
