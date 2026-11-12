<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterSubscriberStatus;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Mail\TransactionalMailer;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class NewsletterSubscriptionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriberRepository $subscribers,
        private SettingsServiceInterface $settings,
        private TransactionalMailer $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function subscribe(string $email): NewsletterSubscriber
    {
        $email = mb_strtolower(trim($email));
        $subscriber = $this->subscribers->findOneByEmail($email);

        if (null === $subscriber) {
            $subscriber = (new NewsletterSubscriber())
                ->setEmail($email)
                ->setToken(bin2hex(random_bytes(32)));
            $this->entityManager->persist($subscriber);
        }

        if (NewsletterSubscriberStatus::Confirmed === $subscriber->getStatus()) {
            return $subscriber;
        }

        if ($this->settings->getBool('newsletter.double_opt_in')) {
            $subscriber->setStatus(NewsletterSubscriberStatus::Pending)->setUnsubscribedAt(null);
            if ('' === $subscriber->getToken()) {
                $subscriber->setToken(bin2hex(random_bytes(32)));
            }
            $this->entityManager->flush();
            $this->sendConfirmation($subscriber);
        } else {
            $subscriber->setStatus(NewsletterSubscriberStatus::Confirmed)
                ->setConfirmedAt(new \DateTimeImmutable())
                ->setUnsubscribedAt(null);
            $this->entityManager->flush();
            $this->sendWelcome($subscriber);
        }

        return $subscriber;
    }

    public function confirm(string $token): ?NewsletterSubscriber
    {
        $subscriber = $this->subscribers->findOneByToken($token);
        if (null === $subscriber) {
            return null;
        }

        if (NewsletterSubscriberStatus::Pending === $subscriber->getStatus()) {
            $subscriber->setStatus(NewsletterSubscriberStatus::Confirmed)->setConfirmedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $subscriber;
    }

    public function unsubscribe(string $token): ?NewsletterSubscriber
    {
        $subscriber = $this->subscribers->findOneByToken($token);
        if (null === $subscriber) {
            return null;
        }

        if (NewsletterSubscriberStatus::Unsubscribed !== $subscriber->getStatus()) {
            $subscriber->setStatus(NewsletterSubscriberStatus::Unsubscribed)->setUnsubscribedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $subscriber;
    }

    public function subscribeUrl(NewsletterSubscriber $subscriber): string
    {
        return $this->urls->generate('front_newsletter_confirm', ['token' => $subscriber->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function unsubscribeUrl(NewsletterSubscriber $subscriber): string
    {
        return $this->urls->generate('front_newsletter_unsubscribe', ['token' => $subscriber->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        $unsubscribe = $this->unsubscribeUrl($subscriber);
        $this->mailer->send($subscriber->getEmail(), 'Confirm your Fieldnotes newsletter subscription', 'emails/newsletter_confirm.html.twig', [
            'subscriber' => $subscriber,
            'confirmUrl' => $this->subscribeUrl($subscriber),
            'unsubscribeUrl' => $unsubscribe,
        ], ['List-Unsubscribe' => '<'.$unsubscribe.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    private function sendWelcome(NewsletterSubscriber $subscriber): void
    {
        $unsubscribe = $this->unsubscribeUrl($subscriber);
        $this->mailer->send($subscriber->getEmail(), 'Welcome to the Fieldnotes Sunday letter', 'emails/newsletter_welcome.html.twig', [
            'subscriber' => $subscriber,
            'unsubscribeUrl' => $unsubscribe,
        ], ['List-Unsubscribe' => '<'.$unsubscribe.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }
}
