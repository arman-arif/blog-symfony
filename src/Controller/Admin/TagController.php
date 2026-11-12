<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tags', name: 'admin_tag_')]
final class TagController extends ContentPreviewController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'tag', 'admin/taxonomy/index.html.twig');
    }

    #[Route('/new', name: 'create', methods: ['GET'])]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Request $request, ?int $id = null): Response
    {
        return $this->screen($request, 'tag', 'admin/taxonomy/form.html.twig', $id);
    }
}
