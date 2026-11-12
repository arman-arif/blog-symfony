<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NewsletterCampaign;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Form\NewsletterCampaignType;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Newsletter\NewsletterCampaignService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/newsletter', name: 'admin_newsletter_')]
#[IsGranted('newsletter.manage')]
final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $subscribers,
        private readonly NewsletterCampaignRepository $campaigns,
        private readonly NewsletterCampaignService $campaignService,
        private readonly ActivityLogger $activityLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function subscribers(Request $request): Response
    {
        $filters = ['status' => $request->query->getString('status'), 'q' => trim($request->query->getString('q'))];
        $page = max(1, $request->query->getInt('page', 1));
        $result = $this->subscribers->findForAdmin($filters, $page);

        return $this->render('admin/newsletter/subscribers.html.twig', [
            'subscribers' => $result['items'], 'total' => $result['total'], 'pages' => $result['pages'], 'page' => $page,
            'filters' => $filters, 'statuses' => NewsletterSubscriberStatus::cases(),
            'counts' => [
                'all' => count($this->subscribers->findAll()),
                'confirmed' => $this->subscribers->countByStatus(NewsletterSubscriberStatus::Confirmed),
                'pending' => $this->subscribers->countByStatus(NewsletterSubscriberStatus::Pending),
                'unsubscribed' => $this->subscribers->countByStatus(NewsletterSubscriberStatus::Unsubscribed),
            ],
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        $subscribers = $this->subscribers->findAllForExport();
        $handle = fopen('php://temp', 'wb');
        \assert(false !== $handle);
        fputcsv($handle, ['id', 'email', 'status', 'created_at', 'confirmed_at', 'unsubscribed_at'], ',', '"', '');
        foreach ($subscribers as $subscriber) {
            fputcsv($handle, [
                $subscriber->getId(), $subscriber->getEmail(), $subscriber->getStatus()->value,
                $subscriber->getCreatedAt()->format(\DateTimeInterface::ATOM),
                $subscriber->getConfirmedAt()?->format(\DateTimeInterface::ATOM),
                $subscriber->getUnsubscribedAt()?->format(\DateTimeInterface::ATOM),
            ], ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="newsletter-subscribers-'.date('Ymd').'.csv"',
        ]);
    }

    #[Route('/subscribers/{id}/{status}', name: 'subscriber_status', requirements: ['id' => '\d+', 'status' => 'confirmed|unsubscribed|pending'], methods: ['POST'])]
    public function subscriberStatus(int $id, string $status, Request $request): Response
    {
        $subscriber = $this->subscribers->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('subscriber_status', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $target = NewsletterSubscriberStatus::from($status);
        $subscriber->setStatus($target);
        if (NewsletterSubscriberStatus::Confirmed === $target) {
            $subscriber->setConfirmedAt(new \DateTimeImmutable())->setUnsubscribedAt(null);
        } elseif (NewsletterSubscriberStatus::Unsubscribed === $target) {
            $subscriber->setUnsubscribedAt(new \DateTimeImmutable());
        }
        $this->entityManager->flush();
        $this->activityLogger->log('newsletter.subscriber_'.$target->value, 'NewsletterSubscriber', $id, ['summary' => 'Changed subscriber status']);
        $this->addFlash('success', 'Subscriber status updated.');

        return $this->redirectToRoute('admin_newsletter_index');
    }

    #[Route('/campaigns', name: 'campaigns', methods: ['GET'])]
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function campaigns(Request $request): Response
    {
        $history = 'admin_newsletter_history' === $request->attributes->get('_route');
        $items = $this->campaigns->findBy($history ? ['status' => NewsletterCampaignStatus::Sent] : [], ['createdAt' => 'DESC']);

        return $this->render('admin/newsletter/campaigns.html.twig', ['campaigns' => $items, 'history' => $history]);
    }

    #[Route('/campaigns/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->campaignForm($request, new NewsletterCampaign());
    }

    #[Route('/campaigns/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->campaignForm($request, $this->findCampaign($id));
    }

    #[Route('/campaigns/{id}/test', name: 'test', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function test(int $id, Request $request, ValidatorInterface $validator): Response
    {
        $campaign = $this->findCampaign($id);
        if (!$this->isCsrfTokenValid('campaign_test', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $email = trim($request->request->getString('email'));
        $violations = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (count($violations) > 0) {
            $this->addFlash('danger', 'Enter a valid test recipient.');
        } else {
            $this->campaignService->sendTest($campaign, $email);
            $this->activityLogger->log('newsletter.test_sent', 'NewsletterCampaign', $id, ['summary' => 'Sent a test campaign email']);
            $this->addFlash('success', 'Test email sent.');
        }

        return $this->redirectToRoute('admin_newsletter_edit', ['id' => $id]);
    }

    #[Route('/campaigns/{id}/send', name: 'send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(int $id, Request $request): Response
    {
        $campaign = $this->findCampaign($id);
        if (!$this->isCsrfTokenValid('campaign_send', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $count = $this->campaignService->queue($campaign);
        $this->activityLogger->log('newsletter.campaign_queued', 'NewsletterCampaign', $id, ['summary' => 'Queued campaign for '.$count.' subscribers']);
        $this->addFlash('success', 'Campaign queued for '.$count.' confirmed subscribers.');

        return $this->redirectToRoute('admin_newsletter_campaigns');
    }

    private function campaignForm(Request $request, NewsletterCampaign $campaign): Response
    {
        $form = $this->createForm(NewsletterCampaignType::class, $campaign);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (NewsletterCampaignStatus::Scheduled === $campaign->getStatus() && null === $campaign->getScheduledAt()) {
                $form->addError(new \Symfony\Component\Form\FormError('Choose a date and time for the scheduled campaign.'));
            } else {
                $this->campaignService->save($campaign);
                $this->activityLogger->log('newsletter.campaign_saved', 'NewsletterCampaign', $campaign->getId(), ['summary' => 'Saved campaign “'.$campaign->getSubject().'”']);
                $this->addFlash('success', 'Campaign saved.');

                return $this->redirectToRoute('admin_newsletter_edit', ['id' => $campaign->getId()]);
            }
        }

        return $this->render('admin/newsletter/form.html.twig', ['campaign' => $campaign, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function findCampaign(int $id): NewsletterCampaign
    {
        return $this->campaigns->find($id) ?? throw $this->createNotFoundException();
    }
}
