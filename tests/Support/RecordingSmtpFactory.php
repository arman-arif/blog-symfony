<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Only the external SMTP boundary is faked; the real mailer/bus/handler stay wired. */
#[AutoconfigureTag('mailer.transport_factory', ['priority' => 1000])]
final class RecordingSmtpFactory implements TransportFactoryInterface
{
    public static bool $enabled = false;
    /** @var list<Dsn> */
    public static array $configurations = [];
    /** @var list<string> */
    public static array $attempts = [];
    /** @var list<SentMessage> */
    public static array $accepted = [];
    /** @var list<string> */
    public static array $failTo = [];

    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public static function reset(): void
    {
        self::$enabled = false;
        self::$configurations = self::$attempts = self::$accepted = self::$failTo = [];
    }

    /** @return list<Dsn>
     * @phpstan-impure SMTP workers mutate this state between assertions.
     */
    public static function configurations(): array
    {
        return self::$configurations;
    }

    /** @return list<string>
     * @phpstan-impure SMTP workers mutate this state between assertions.
     */
    public static function attempts(): array
    {
        return self::$attempts;
    }

    public function supports(Dsn $dsn): bool
    {
        return self::$enabled && in_array($dsn->getScheme(), ['smtp', 'smtps', 'sendmail'], true);
    }

    public function create(Dsn $dsn): TransportInterface
    {
        self::$configurations[] = $dsn;

        return new class($this->dispatcher) extends AbstractTransport {
            public function __toString(): string
            {
                return 'smtp://controlled-test';
            }

            protected function doSend(SentMessage $message): void
            {
                $recipient = $message->getEnvelope()->getRecipients()[0]->getAddress();
                RecordingSmtpFactory::$attempts[] = $recipient;
                if (in_array($recipient, RecordingSmtpFactory::$failTo, true)) {
                    throw new TransportException('Controlled SMTP failure.');
                }
                RecordingSmtpFactory::$accepted[] = $message;
            }
        };
    }
}
