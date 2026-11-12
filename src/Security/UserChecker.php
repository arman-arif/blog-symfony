<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserChecker implements UserCheckerInterface
{
    public function __construct(private SettingsServiceInterface $settings)
    {
    }

    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive() || null !== $user->getDeletedAt()) {
            throw new CustomUserMessageAccountStatusException('Your account is not active. Contact an administrator.');
        }

        if ($this->settings->getBool('security.require_admin_2fa')
            && $user->hasRoleCode('ROLE_SUPER_ADMIN')
            && !$user->isTotpAuthenticationEnabled()
        ) {
            throw new CustomUserMessageAccountStatusException('Two-factor authentication is required for administrator accounts.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
