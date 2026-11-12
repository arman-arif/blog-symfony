<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories', name: 'admin_category_')]
#[IsGranted('category.manage')]
final class CategoryController extends ContentPreviewController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'category', 'admin/taxonomy/index.html.twig');
    }

    #[Route('/new', name: 'create', methods: ['GET'])]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Request $request, ?int $id = null): Response
    {
        return $this->screen($request, 'category', 'admin/taxonomy/form.html.twig', $id);
    }
}
