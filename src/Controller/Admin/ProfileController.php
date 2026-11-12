<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('profile.manage.own')]
final class ProfileController extends ContentPreviewController
{
    #[Route('/admin/profile', name: 'admin_profile_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->screen($request, 'user', 'admin/profile/index.html.twig', 1);
    }
}
