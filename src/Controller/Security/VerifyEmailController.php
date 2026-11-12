<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Repository\UserRepository;
use App\Service\Audit\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class VerifyEmailController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(
        Request $request,
        UserRepository $users,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
    ): Response {
        $id = $request->query->get('id');
        $user = is_numeric($id) ? $users->find((int) $id) : null;
        if (null === $user) {
            $this->addFlash('danger', 'This verification link is no longer valid. Please request a new one.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('danger', $exception->getReason());

            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $entityManager->flush();
            $activityLogger->log('user.email_verified', 'User', $user->getId(), ['summary' => 'Confirmed email address']);
        }

        $this->addFlash('success', 'Your email address is confirmed. You can sign in now.');

        return $this->redirectToRoute('app_login');
    }
}
