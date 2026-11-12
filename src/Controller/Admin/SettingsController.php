<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('admin.access')]
final class SettingsController extends ContentPreviewController
{
    #[Route('/admin/settings', name: 'admin_settings_index', methods: ['GET'])]
    #[IsGranted('settings.general.manage')]
    public function general(Request $request): Response
    {
        return $this->screen($request, 'settings', 'admin/settings/general.html.twig');
    }

    #[Route('/admin/appearance', name: 'admin_appearance_index', methods: ['GET'])]
    #[IsGranted('settings.appearance.manage')]
    public function appearance(Request $request): Response
    {
        return $this->screen($request, 'appearance', 'admin/appearance/index.html.twig');
    }

    #[Route('/admin/seo', name: 'admin_seo_index', methods: ['GET'])]
    #[IsGranted('settings.seo.manage')]
    public function seo(Request $request): Response
    {
        return $this->screen($request, 'seo', 'admin/seo/index.html.twig');
    }

    #[Route('/admin/settings/smtp', name: 'admin_settings_smtp', methods: ['GET'])]
    #[IsGranted('settings.smtp.manage')]
    public function smtp(Request $request): Response
    {
        return $this->screen($request, 'smtp', 'admin/settings/smtp.html.twig');
    }
}
