<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/newsletter', name: 'admin_newsletter_')]
#[IsGranted('newsletter.manage')]
final class NewsletterController extends ContentPreviewController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function subscribers(Request $request): Response
    {
        return $this->screen($request, 'subscriber', 'admin/newsletter/subscribers.html.twig');
    }

    #[Route('/campaigns', name: 'campaigns', methods: ['GET'])]
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function campaigns(Request $request): Response
    {
        return $this->screen($request, 'campaign', 'admin/newsletter/campaigns.html.twig');
    }

    #[Route('/campaigns/new', name: 'create', methods: ['GET'])]
    #[Route('/campaigns/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Request $request, ?int $id = null): Response
    {
        return $this->screen($request, 'campaign', 'admin/newsletter/form.html.twig', $id);
    }
}
