<?php

declare(strict_types=1);

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final readonly class SettingsMailer implements MailerInterface
{
    public function __construct(
        #[Autowire(service: 'mailer.mailer')]
        private MailerInterface $defaultMailer,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->defaultMailer->send($message, $envelope);
    }
}
