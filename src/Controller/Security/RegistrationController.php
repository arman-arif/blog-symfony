<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Mail\TransactionalMailer;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        SettingsServiceInterface $settings,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        VerifyEmailHelperInterface $verifyEmailHelper,
        TransactionalMailer $mailer,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$settings->getBool('registration.enabled')) {
            throw new NotFoundHttpException('Registration is disabled.');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            if (null !== $users->findOneByEmail($email)) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('An account with this email already exists.'));
            } else {
                $user = (new User())
                    ->setEmail($email)
                    ->setDisplayName((string) $form->get('displayName')->getData())
                    ->setIsActive(true)
                    ->setIsVerified(false);
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));

                $entityManager->persist($user);
                $entityManager->flush();

                $signature = $verifyEmailHelper->generateSignature(
                    'app_verify_email',
                    (string) $user->getId(),
                    $user->getEmail(),
                    ['id' => $user->getId()],
                );
                $mailer->send($user->getEmail(), 'Confirm your Fieldnotes account', 'emails/verify_email.html.twig', [
                    'user' => $user,
                    'signedUrl' => $signature->getSignedUrl(),
                ]);
                $activityLogger->log('user.registered', 'User', $user->getId(), ['summary' => 'Created a reader account']);

                $this->addFlash('success', 'Check your inbox to confirm your email address.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }
}
