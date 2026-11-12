<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute($this->isGranted('admin.access') ? 'admin_dashboard_index' : 'front_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/2fa', name: '2fa_login', methods: ['GET'])]
    public function twoFactor(): Response
    {
        return $this->render('security/2fa.html.twig');
    }

    #[Route('/2fa/check', name: '2fa_login_check', methods: ['POST'])]
    public function twoFactorCheck(): Response
    {
        return $this->redirectToRoute('2fa_login');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This route is intercepted by the security firewall.');
    }
}
