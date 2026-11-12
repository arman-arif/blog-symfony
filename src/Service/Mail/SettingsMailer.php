<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\RawMessage;

final class SettingsMailer implements MailerInterface
{
    private ?MailerInterface $settingsMailer = null;

    public function __construct(
        private readonly SettingsServiceInterface $settings,
        #[Autowire(service: 'mailer.mailer')]
        private readonly MailerInterface $defaultMailer,
        private readonly MessageBusInterface $bus,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->mailer()->send($message, $envelope);
    }

    private function mailer(): MailerInterface
    {
        $transport = $this->settings->getString('mail.transport');
        if ('env' === $transport) {
            return $this->defaultMailer;
        }
        if (null !== $this->settingsMailer) {
            return $this->settingsMailer;
        }

        $dsn = 'sendmail' === $transport
            ? 'sendmail://default'
            : $this->smtpDsn();
        $transport = Transport::fromDsn($dsn, $this->dispatcher);

        return $this->settingsMailer = new Mailer($transport, $this->bus, $this->dispatcher);
    }

    private function smtpDsn(): string
    {
        $host = $this->settings->getString('mail.host');
        $port = $this->settings->getInt('mail.port');
        $username = $this->settings->getString('mail.username');
        $password = $this->settings->getString('mail.password');
        $encryption = $this->settings->getString('mail.encryption');
        $auth = '' !== $username ? rawurlencode($username).':'.rawurlencode($password).'@' : '';
        $query = '' !== $encryption ? '?encryption='.rawurlencode($encryption) : '';

        return 'smtp://'.$auth.$host.':'.$port.$query;
    }
}
