<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/menus', name: 'admin_menu_')]
#[IsGranted('menu.manage')]
final class MenuController extends ContentPreviewController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'menu', 'admin/menu/index.html.twig');
    }

    #[Route('/new', name: 'create', methods: ['GET'])]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Request $request, ?int $id = null): Response
    {
        return $this->screen($request, 'menu', 'admin/menu/form.html.twig', $id);
    }
}
