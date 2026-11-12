<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('profile.manage.own')]
final class ProfileController extends AbstractController
{
    #[Route('/admin/profile', name: 'admin_profile_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_profile');
    }
}
