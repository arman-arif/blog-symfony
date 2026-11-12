<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnalyticsController extends ContentPreviewController
{
    #[Route('/admin/analytics', name: 'admin_analytics_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'analytics', 'admin/analytics/index.html.twig');
    }
}
