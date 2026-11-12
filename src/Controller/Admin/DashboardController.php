<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Mock\AdminPreview;
use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
final class DashboardController extends AbstractController
{
    public function __construct(private readonly AdminPreview $preview, private readonly MockDataProvider $data)
    {
    }

    #[Route('', name: 'dashboard_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->preview->requireEnabled();

        return $this->render('admin/dashboard/index.html.twig', [
            'currentUser' => $this->data->users()[0],
            'dashboard' => $this->data->dashboard(),
            'state' => $request->query->getString('state'),
        ]);
    }

    #[Route('/no-permission', name: 'permission_denied', methods: ['GET'])]
    public function noPermission(): Response
    {
        $this->preview->requireEnabled();

        return $this->render('admin/dashboard/no_permission.html.twig', ['currentUser' => $this->data->users()[0]], new Response(status: Response::HTTP_FORBIDDEN));
    }
}
