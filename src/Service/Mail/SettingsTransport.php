<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/** The framework mailer AND its Messenger handler resolve settings at delivery time. */
#[AsDecorator(decorates: 'mailer.transports')]
final class SettingsTransport implements TransportInterface
{
    private ?TransportInterface $configured = null;
    private ?string $configurationHash = null;

    public function __construct(
        #[AutowireDecorated]
        private readonly TransportInterface $fallback,
        private readonly SettingsServiceInterface $settings,
        #[Autowire(service: 'mailer.transport_factory')]
        private readonly Transport $factory,
    ) {
    }

    public function __toString(): string
    {
        // Queueing must not resolve credentials or open a connection.
        return 'settings://runtime';
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $mode = $this->settings->getString('mail.transport');
        if ('env' === $mode) {
            return $this->fallback->send($message, $envelope);
        }
        $dsn = match ($mode) {
            'sendmail' => new Dsn('sendmail', 'default'),
            'smtp' => $this->smtpDsn(),
            default => throw new \InvalidArgumentException('Unsupported saved mail transport.'),
        };
        $hash = hash('sha256', serialize($dsn));
        if ($hash !== $this->configurationHash || null === $this->configured) {
            $this->configured = $this->factory->fromDsnObject($dsn);
            $this->configurationHash = $hash;
        }

        return $this->configured->send($message, $envelope);
    }

    private function smtpDsn(): Dsn
    {
        $host = trim($this->settings->getString('mail.host'));
        $port = $this->settings->getInt('mail.port');
        if ('' === $host || preg_match('/[\s\/@?#]/', $host) || $port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Configure a valid SMTP host and port.');
        }
        $encryption = $this->settings->getString('mail.encryption');
        $options = match ($encryption) {
            'tls' => ['require_tls' => true],
            'ssl' => [],
            '' => ['auto_tls' => false],
            default => throw new \InvalidArgumentException('Unsupported SMTP encryption setting.'),
        };

        return new Dsn(
            'ssl' === $encryption ? 'smtps' : 'smtp', $host,
            $this->settings->getString('mail.username') ?: null,
            $this->settings->getString('mail.password') ?: null,
            $port, $options,
        );
    }
}
