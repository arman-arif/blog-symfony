<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('activity_log.view')]
final class ActivityController extends ContentPreviewController
{
    #[Route('/admin/activity', name: 'admin_activity_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'activity', 'admin/activity/index.html.twig');
    }
}
