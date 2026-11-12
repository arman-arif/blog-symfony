<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Analytics\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('admin.access')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard_index', methods: ['GET'])]
    public function index(AnalyticsService $analytics): Response
    {
        return $this->render('admin/dashboard/index.html.twig', ['dashboard' => $analytics->dashboard()]);
    }

    #[Route('/no-permission', name: 'permission_denied', methods: ['GET'])]
    public function noPermission(): Response
    {
        return $this->render('admin/dashboard/no_permission.html.twig', [], new Response(status: Response::HTTP_FORBIDDEN));
    }
}
