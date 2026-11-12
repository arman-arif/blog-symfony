<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('activity_log.view')]
final class ActivityController extends AbstractController
{
    #[Route('/admin/activity', name: 'admin_activity_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activity): Response
    {
        $filters = ['q' => $request->query->getString('q'), 'action' => $request->query->getString('action')];
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render('admin/activity/index.html.twig', [
            'activity' => $activity->paginate($filters, $page),
            'filters' => $filters,
            'page' => $page,
            'actions' => $activity->distinctActions(),
        ]);
    }
}
