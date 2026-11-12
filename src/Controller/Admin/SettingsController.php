<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\SettingsFormType;
use App\Service\Audit\ActivityLogger;
use App\Service\Mail\SettingsMailer;
use App\Service\Settings\DatabaseSettingsService;
use App\Service\Settings\SettingsCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly DatabaseSettingsService $settings,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('/admin/settings', name: 'admin_settings_index', methods: ['GET', 'POST'])]
    #[Route('/admin/settings/general', name: 'admin_settings_general', methods: ['GET', 'POST'])]
    #[IsGranted('settings.general.manage')]
    public function general(Request $request): Response
    {
        return $this->editGroup($request, 'general');
    }

    #[Route('/admin/appearance', name: 'admin_appearance_index', methods: ['GET', 'POST'])]
    #[IsGranted('settings.appearance.manage')]
    public function appearance(Request $request): Response
    {
        return $this->editGroup($request, 'appearance');
    }

    #[Route('/admin/seo', name: 'admin_seo_index', methods: ['GET', 'POST'])]
    #[IsGranted('settings.seo.manage')]
    public function seo(Request $request): Response
    {
        return $this->editGroup($request, 'seo');
    }

    #[Route('/admin/settings/smtp', name: 'admin_settings_smtp', methods: ['GET', 'POST'])]
    #[IsGranted('settings.smtp.manage')]
    public function smtp(Request $request): Response
    {
        return $this->editGroup($request, 'smtp');
    }

    #[Route('/admin/settings/code', name: 'admin_settings_code', methods: ['GET', 'POST'])]
    #[IsGranted('settings.code.manage')]
    public function code(Request $request): Response
    {
        return $this->editGroup($request, 'code');
    }

    #[Route('/admin/settings/smtp/test', name: 'admin_settings_smtp_test', methods: ['POST'])]
    #[IsGranted('settings.smtp.manage')]
    public function testSmtp(Request $request, SettingsMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('smtp_test', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $email = trim((string) $request->request->get('email'));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Enter a valid test recipient.');

            return $this->redirectToRoute('admin_settings_smtp');
        }
        try {
            $mailer->send((new Email())->from($this->settings->getString('mail.from_address') ?: 'no-reply@fieldnotes.test')
                ->to($email)->subject('Fieldnotes SMTP test')->text('This is a test message from your Fieldnotes settings.'));
            $this->addFlash('success', 'Test email queued for '.$email.'.');
        } catch (TransportExceptionInterface|\InvalidArgumentException $exception) {
            $this->addFlash('danger', 'The test email could not be sent: '.$exception->getMessage());
        }

        return $this->redirectToRoute('admin_settings_smtp');
    }

    private function editGroup(Request $request, string $group): Response
    {
        $form = $this->createForm(SettingsFormType::class, $this->settings->valuesForGroup($group), ['group' => $group]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settings->saveGroup($group, $form->getData());
            $this->activityLogger->log('settings.updated', 'Settings', null, ['summary' => 'Updated '.SettingsCatalog::GROUPS[$group].' settings', 'group' => $group]);
            $this->addFlash('success', SettingsCatalog::GROUPS[$group].' settings saved.');

            return $this->redirectToRoute(match ($group) {
                'appearance' => 'admin_appearance_index',
                'seo' => 'admin_seo_index',
                'smtp' => 'admin_settings_smtp',
                'code' => 'admin_settings_code',
                default => 'admin_settings_index',
            });
        }

        return $this->render('admin/settings/page.html.twig', [
            'form' => $form,
            'group' => $group,
            'group_label' => SettingsCatalog::GROUPS[$group],
            'groups' => SettingsCatalog::GROUPS,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
