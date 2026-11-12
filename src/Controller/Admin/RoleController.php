<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('admin.access')]
final class RoleController extends ContentPreviewController
{
    #[Route('/admin/roles', name: 'admin_role_index', methods: ['GET'])]
    #[IsGranted('role.manage')]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'role', 'admin/role/index.html.twig');
    }

    #[Route('/admin/roles/new', name: 'admin_role_create', methods: ['GET'])]
    #[Route('/admin/roles/{id}/edit', name: 'admin_role_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('role.manage')]
    public function form(Request $request, ?int $id = null): Response
    {
        return $this->screen($request, 'role', 'admin/role/form.html.twig', $id);
    }

    #[Route('/admin/permissions', name: 'admin_permission_index', methods: ['GET'])]
    #[IsGranted('permission.manage')]
    public function permissions(Request $request): Response
    {
        return $this->screen($request, 'role', 'admin/role/permissions.html.twig');
    }
}
