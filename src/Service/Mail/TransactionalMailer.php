<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class TransactionalMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private SettingsServiceInterface $settings,
        #[Autowire(service: 'mailer.transports')]
        private TransportInterface $deliveryTransport,
    ) {
    }

    /** @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function send(string $to, string $subject, string $template, array $context = [], array $headers = []): void
    {
        $this->mailer->send($this->message($to, $subject, $template, $context, $headers));
    }

    /** Used inside an already-async campaign batch; success means transport acceptance, not enqueueing.
     * @param array<string, mixed>  $context
     * @param array<string, string> $headers
     */
    public function deliver(string $to, string $subject, string $template, array $context = [], array $headers = []): void
    {
        if (null === $this->deliveryTransport->send($this->message($to, $subject, $template, $context, $headers))) {
            throw new \RuntimeException('The mail transport rejected this message.');
        }
    }

    /** @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    private function message(string $to, string $subject, string $template, array $context, array $headers): Email
    {
        $fromAddress = $this->settings->getString('mail.from_address');
        $fromName = $this->settings->getString('mail.from_name');

        $email = (new Email())
            ->from(new Address('' === $fromAddress ? 'no-reply@fieldnotes.test' : $fromAddress, $fromName))
            ->to($to)
            ->subject($subject)
            ->html($this->twig->render($template, $context + ['siteName' => $this->settings->getString('general.name'), 'fromName' => $fromName]));

        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        return $email;
    }
}
