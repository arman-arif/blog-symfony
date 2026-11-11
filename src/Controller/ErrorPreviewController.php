<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ErrorPreviewController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.environment%')] private readonly string $environment)
    {
    }

    #[Route('/_maintenance', name: 'front_maintenance_preview', methods: ['GET'])]
    public function maintenance(): Response
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/page/maintenance.html.twig', [], new Response(status: 503, headers: ['Retry-After' => '3600']));
    }
}
