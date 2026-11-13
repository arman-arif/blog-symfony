<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Form\TwoFactorDisableFormType;
use App\Security\TotpEnrollment;
use App\Service\Audit\ActivityLogger;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly ActivityLogger $activityLogger,
        private readonly SettingsServiceInterface $settings,
        #[Autowire(service: 'limiter.two_factor_changes')]
        private readonly RateLimiterFactory $twoFactorLimiter,
    ) {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->activityLogger->log('profile.updated', 'User', $user->getId(), ['summary' => 'Updated profile details']);
            $this->addFlash('success', 'Your profile has been updated.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->renderProfile($request, $user, $form, $this->createForm(ChangePasswordFormType::class));
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->currentUser();
        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = (string) $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $current)) {
                $form->get('currentPassword')->addError(new FormError('Your current password is incorrect.'));
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('newPassword')->getData()));
                $this->entityManager->flush();
                $this->activityLogger->log('profile.password_changed', 'User', $user->getId(), ['summary' => 'Changed account password']);
                $this->addFlash('success', 'Your password has been changed.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->renderProfile($request, $user, $profileForm, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/profile/2fa/start', name: 'app_profile_2fa_start', methods: ['POST'])]
    public function startTwoFactor(Request $request): Response
    {
        $this->assertCsrf('2fa_start', $request);
        if ($this->currentUser()->isTotpAuthenticationEnabled()) {
            throw new AccessDeniedException('Disable the existing authenticator with reauthentication before replacing it.');
        }
        $request->getSession()->set('totp_pending_secret', $this->totpAuthenticator->generateSecret());
        $this->addFlash('info', 'Scan or copy the setup key into your authenticator app, then confirm the six-digit code.');

        return $this->redirectToRoute('app_profile', ['_fragment' => 'two-factor']);
    }

    #[Route('/profile/2fa/confirm', name: 'app_profile_2fa_confirm', methods: ['POST'])]
    public function confirmTwoFactor(Request $request): Response
    {
        $this->assertCsrf('2fa_confirm', $request);
        $user = $this->currentUser();
        if ($user->isTotpAuthenticationEnabled()) {
            throw new AccessDeniedException('An authenticator is already enrolled.');
        }
        $this->assertTwoFactorRateLimit($user);
        $secret = $request->getSession()->get('totp_pending_secret');

        if (!is_string($secret) || '' === $secret) {
            $this->addFlash('danger', 'Start two-factor setup again to generate a new key.');

            return $this->redirectToRoute('app_profile');
        }

        $code = trim((string) $request->request->get('code'));
        $enrollment = new TotpEnrollment($secret, $user->getEmail());
        if (!$this->totpAuthenticator->checkCode($enrollment, $code)) {
            $this->addFlash('danger', 'That code did not match. Check your authenticator app and try again.');

            return $this->redirectToRoute('app_profile', ['_fragment' => 'two-factor']);
        }

        $user->setTotpSecret($secret);
        $this->entityManager->flush();
        $request->getSession()->remove('totp_pending_secret');
        $this->activityLogger->log('profile.two_factor_enabled', 'User', $user->getId(), ['summary' => 'Enabled TOTP two-factor authentication']);
        $this->addFlash('success', 'Two-factor authentication is now enabled.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/2fa/disable', name: 'app_profile_2fa_disable', methods: ['POST'])]
    public function disableTwoFactor(Request $request): Response
    {
        $user = $this->currentUser();
        if ($this->isTwoFactorRequired($user)) {
            throw new AccessDeniedException('Two-factor authentication is required for administrator accounts.');
        }
        $form = $this->createForm(TwoFactorDisableFormType::class);
        $form->handleRequest($request);
        $this->assertTwoFactorRateLimit($user);
        if ($form->isSubmitted() && $form->isValid() && $user->isTotpAuthenticationEnabled()) {
            if ($this->totpAuthenticator->checkCode($user, (string) $form->get('code')->getData())) {
                $user->setTotpSecret(null);
                $this->entityManager->flush();
                $request->getSession()->remove('totp_pending_secret');
                $this->activityLogger->log('profile.two_factor_disabled', 'User', $user->getId(), ['summary' => 'Disabled TOTP two-factor authentication']);
                $this->addFlash('success', 'Two-factor authentication has been disabled.');

                return $this->redirectToRoute('app_profile');
            }
            $form->get('code')->addError(new FormError('That authenticator code did not match.'));
        }

        return $this->renderProfile($request, $user, $this->createForm(ProfileFormType::class, $user), $this->createForm(ChangePasswordFormType::class), Response::HTTP_UNPROCESSABLE_ENTITY, $form);
    }

    /** @param FormInterface<mixed> $profileForm
     * @param FormInterface<mixed>      $passwordForm
     * @param FormInterface<mixed>|null $disableForm
     */
    private function renderProfile(Request $request, User $user, FormInterface $profileForm, FormInterface $passwordForm, int $status = Response::HTTP_OK, ?FormInterface $disableForm = null): Response
    {
        $pendingSecret = $request->getSession()->get('totp_pending_secret');
        $qrContent = is_string($pendingSecret) && '' !== $pendingSecret
            ? $this->totpAuthenticator->getQRContent(new TotpEnrollment($pendingSecret, $user->getEmail()))
            : null;

        return $this->render('security/profile.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'pendingSecret' => $pendingSecret,
            'qrContent' => $qrContent,
            'disableForm' => $disableForm ?? $this->createForm(TwoFactorDisableFormType::class),
            'twoFactorRequired' => $this->isTwoFactorRequired($user),
        ], new Response(status: $status));
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user;
    }

    private function assertCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
    }

    private function isTwoFactorRequired(User $user): bool
    {
        return $this->settings->getBool('security.require_admin_2fa')
            && ($user->hasRoleCode('ROLE_SUPER_ADMIN') || $user->hasRoleCode('ROLE_ADMIN'));
    }

    private function assertTwoFactorRateLimit(User $user): void
    {
        if (!$this->twoFactorLimiter->create('user-'.$user->getId())->consume()->isAccepted()) {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(60, 'Too many authenticator attempts. Try again shortly.');
        }
    }
}
