<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Enum\NewsletterSubscriberStatus;
use App\Service\Newsletter\NewsletterSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NewsletterController extends AbstractController
{
    #[Route('/newsletter/subscribe', name: 'front_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriptionService $subscriptions,
        ValidatorInterface $validator,
        #[Autowire(service: 'limiter.newsletter_subscribe')] RateLimiterFactory $limiter,
    ): Response {
        if (!$this->isCsrfTokenValid('newsletter_subscribe', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Your session expired. Please try again.');

            return $this->redirect($this->generateUrl('front_home').'#newsletter');
        }
        if (!$limiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
            $this->addFlash('warning', 'Too many subscription attempts. Please try again later.');

            return $this->redirect($this->generateUrl('front_home').'#newsletter');
        }

        $email = trim($request->request->getString('email'));
        $violations = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]);
        if (count($violations) > 0) {
            $this->addFlash('danger', 'Enter a valid email address.');

            return $this->redirect($this->generateUrl('front_home').'#newsletter');
        }

        $subscriber = $subscriptions->subscribe($email);
        $message = match ($subscriber->getStatus()) {
            NewsletterSubscriberStatus::Confirmed => 'You are on the list. Keep an eye on your inbox.',
            NewsletterSubscriberStatus::Pending => 'Check your inbox to confirm your subscription.',
            default => 'Your subscription preference has been updated.',
        };
        $this->addFlash('success', $message);

        return $this->redirect($this->generateUrl('front_home').'#newsletter');
    }

    #[Route('/newsletter/confirm/{token}', name: 'front_newsletter_confirm', requirements: ['token' => '[a-f0-9]{32,64}'], methods: ['GET'])]
    public function confirm(string $token, NewsletterSubscriptionService $subscriptions): Response
    {
        $subscriber = $subscriptions->confirm($token);
        if (null === $subscriber) {
            $this->addFlash('danger', 'This confirmation link is not valid.');
        } else {
            $this->addFlash('success', 'Your subscription is confirmed. Welcome to the Sunday letter.');
        }

        return $this->redirect($this->generateUrl('front_home').'#newsletter');
    }

    #[Route('/newsletter/unsubscribe/{token}', name: 'front_newsletter_unsubscribe', requirements: ['token' => '[a-f0-9]{32,64}'], methods: ['GET'])]
    public function unsubscribe(string $token, NewsletterSubscriptionService $subscriptions): Response
    {
        $subscriber = $subscriptions->unsubscribe($token);
        if (null === $subscriber) {
            $this->addFlash('danger', 'This unsubscribe link is not valid.');
        } else {
            $this->addFlash('success', 'You have been unsubscribed from the Sunday letter.');
        }

        return $this->redirect($this->generateUrl('front_home').'#newsletter');
    }
}
