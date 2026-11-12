<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Mail\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $users,
        ResetPasswordHelperInterface $resetPasswordHelper,
        TransactionalMailer $mailer,
        ActivityLogger $activityLogger,
    ): Response {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $users->findOneByEmail($email);

            if (null !== $user) {
                try {
                    $resetToken = $resetPasswordHelper->generateResetToken($user);
                    $mailer->send($user->getEmail(), 'Reset your Fieldnotes password', 'emails/reset_password.html.twig', [
                        'user' => $user,
                        'resetToken' => $resetToken,
                        'expiresAt' => $resetToken->getExpiresAt(),
                    ]);
                    $activityLogger->log('user.password_reset_requested', 'User', $user->getId(), ['summary' => 'Requested a password reset link']);
                } catch (ResetPasswordExceptionInterface $exception) {
                    $this->addFlash('warning', $exception->getReason());
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('security/request_reset.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('security/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        string $token,
        ResetPasswordHelperInterface $resetPasswordHelper,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        try {
            $user = $resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $exception) {
            $this->addFlash('danger', $exception->getReason());

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert($user instanceof \App\Entity\User);
            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $entityManager->flush();
            $resetPasswordHelper->removeResetRequest($token);
            $activityLogger->log('user.password_reset', 'User', $user->getId(), ['summary' => 'Reset account password']);
            $this->addFlash('success', 'Your password has been reset. Sign in with your new password.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset.html.twig', ['form' => $form, 'token' => $token]);
    }
}
