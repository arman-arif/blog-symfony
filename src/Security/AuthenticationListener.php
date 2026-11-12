<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\Audit\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class AuthenticationListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->activityLogger->log('user.login', 'User', $user->getId(), [
            'summary' => 'Signed in to the editorial workspace',
        ]);
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activityLogger->log('user.logout', 'User', $user->getId(), [
            'summary' => 'Signed out of the editorial workspace',
        ]);
    }
}
