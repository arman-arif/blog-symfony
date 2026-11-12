<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class TransactionalMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private SettingsServiceInterface $settings,
    ) {
    }

    /** @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function send(string $to, string $subject, string $template, array $context = [], array $headers = []): void
    {
        $fromAddress = $this->settings->getString('mail.from_address');
        $fromName = $this->settings->getString('mail.from_name');

        $email = (new Email())
            ->from('' === $fromAddress ? 'no-reply@fieldnotes.test' : $fromAddress)
            ->to($to)
            ->subject($subject)
            ->html($this->twig->render($template, $context + ['siteName' => $this->settings->getString('general.name'), 'fromName' => $fromName]));

        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        $this->mailer->send($email);
    }
}
