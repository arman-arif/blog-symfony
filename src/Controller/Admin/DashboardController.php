<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
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
        $user = $this->getUser();
        \assert($user instanceof User);
        $dashboard = $analytics->dashboard($this->isGranted('article.edit.any') ? null : $user->getId());
        $dashboard['stats'] = array_values(array_filter($dashboard['stats'], fn (array $stat): bool => match ($stat['label']) {
            'Editorial team' => $this->isGranted('user.manage'),
            'Article views' => $this->isGranted('analytics.view'),
            'Reader conversations' => $this->isGranted('comment.moderate'),
            default => true,
        }));
        if (!$this->isGranted('comment.moderate')) {
            $dashboard['comments'] = [];
        }
        if (!$this->isGranted('activity_log.view')) {
            $dashboard['activity'] = [];
        }
        if (!$this->isGranted('newsletter.manage')) {
            $dashboard['subscribers'] = 0;
        }

        return $this->render('admin/dashboard/index.html.twig', ['dashboard' => $dashboard]);
    }

    #[Route('/no-permission', name: 'permission_denied', methods: ['GET'])]
    public function noPermission(): Response
    {
        return $this->render('admin/dashboard/no_permission.html.twig', [], new Response(status: Response::HTTP_FORBIDDEN));
    }
}
