<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Setting;
use App\Service\Mail\SettingsMailer;
use App\Service\Mail\SettingsTransport;
use App\Service\Settings\DatabaseSettingsService;
use App\Tests\Support\RecordingSmtpFactory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class SettingsMailDeliveryRegressionTest extends FrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        self::getContainer()->get('cache.content')->clear();
        self::ensureKernelShutdown();
        RecordingSmtpFactory::reset();
    }

    protected function tearDown(): void
    {
        self::getContainer()->get('cache.content')->clear();
        RecordingSmtpFactory::reset();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function encryptionModes(): iterable
    {
        yield 'required STARTTLS' => ['tls', 'smtp'];
        yield 'implicit TLS' => ['ssl', 'smtps'];
        yield 'explicit plaintext' => ['', 'smtp'];
    }

    #[DataProvider('encryptionModes')]
    public function testActualEmailWorkerUsesTheSavedConfigurationAtDeliveryTime(string $encryption, string $scheme): void
    {
        self::createClient();
        RecordingSmtpFactory::$enabled = true;
        $settings = self::getContainer()->get(DatabaseSettingsService::class);
        $settings->saveGroup('smtp', [
            'mail_transport' => 'smtp', 'mail_host' => 'before.example.test', 'mail_port' => 587,
            'mail_username' => 'user@example.test:/?', 'mail_password' => 'password:@/?', 'mail_encryption' => $encryption,
        ]);
        $mailer = self::getContainer()->get(SettingsMailer::class);
        $email = (new Email())->from('sender@example.test')->to('reader@example.test')->subject('Delivery-time settings')->text('A controlled message.');
        $email->getHeaders()->addTextHeader('X-Bus-Transport', 'async');
        $mailer->send($email);
        self::assertSame([], RecordingSmtpFactory::configurations());
        self::assertSame([], RecordingSmtpFactory::attempts());
        $queue = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $queue);
        self::assertCount(1, $queue->getSent());
        self::assertStringNotContainsString('password:@/?', serialize($queue->getSent()[0]));

        $settings->saveGroup('smtp', ['mail_host' => 'after.example.test']);
        $this->consume();
        self::assertCount(1, $queue->getAcknowledged());
        self::assertSame(['reader@example.test'], RecordingSmtpFactory::attempts());
        $dsn = RecordingSmtpFactory::configurations()[0];
        self::assertSame('after.example.test', $dsn->getHost());
        self::assertSame($scheme, $dsn->getScheme());
        self::assertSame('user@example.test:/?', $dsn->getUser());
        self::assertSame('password:@/?', $dsn->getPassword());
        self::assertNull($dsn->getOption('encryption'));
        // Verify the captured configuration is meaningful to the real Symfony SMTP factory.
        $smtp = (new EsmtpTransportFactory())->create($dsn);
        self::assertInstanceOf(EsmtpTransport::class, $smtp);
        self::assertSame('tls' === $encryption, $smtp->isTlsRequired());
        self::assertSame('' !== $encryption, $smtp->isAutoTls());
        $stream = $smtp->getStream();
        self::assertInstanceOf(SocketStream::class, $stream);
        self::assertSame('ssl' === $encryption, $stream->isTLS());

        // A long-lived worker switches transports without a restart, and env remains a fallback.
        $settings->saveGroup('smtp', ['mail_host' => 'changed.example.test']);
        $mailer->send($email);
        $this->consume();
        self::assertSame('changed.example.test', RecordingSmtpFactory::configurations()[1]->getHost());
        $settings->saveGroup('smtp', ['mail_transport' => 'env']);
        $mailer->send($email);
        $this->consume();
        self::assertCount(2, RecordingSmtpFactory::attempts());
    }

    private function consume(): void
    {
        $command = self::getContainer()->get('console.command.messenger_consume_messages');
        self::assertInstanceOf(Command::class, $command);
        self::assertSame(0, (new CommandTester($command))->execute([
            'receivers' => ['async'], '--limit' => 1, '--time-limit' => 5, '--no-reset' => null,
        ], ['interactive' => false]));
    }

    public function testWorkerRefreshesSettingsChangedOutsideItsIdentityMap(): void
    {
        self::createClient();
        RecordingSmtpFactory::$enabled = true;
        $settings = self::getContainer()->get(DatabaseSettingsService::class);
        $settings->saveGroup('smtp', ['mail_transport' => 'smtp', 'mail_host' => 'original.example.test']);
        $transport = self::getContainer()->get(SettingsTransport::class);
        $email = (new Email())->from('sender@example.test')->to('reader@example.test')->subject('External update')->text('Controlled.');
        // Campaign recipients share one worker/container, without a reset between SMTP calls.
        $transport->send($email);
        self::assertSame('original.example.test', RecordingSmtpFactory::configurations()[0]->getHost());

        // Simulate an administrator committing elsewhere, without refreshing worker entities.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('UPDATE '.Setting::class.' setting SET setting.value = :value WHERE setting.key = :key')
            ->setParameter('value', ['value' => 'remote.example.test'], Types::JSON)->setParameter('key', 'mail.host')->execute();
        self::getContainer()->get('cache.content')->invalidateTags(['settings']);
        $transport->send($email);
        self::assertCount(2, RecordingSmtpFactory::configurations());
        self::assertSame('remote.example.test', RecordingSmtpFactory::configurations()[1]->getHost());
    }
}
