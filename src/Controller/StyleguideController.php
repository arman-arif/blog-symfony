<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StyleguideController extends AbstractController
{
    #[Route('/_styleguide', name: 'styleguide', methods: ['GET'])]
    public function index(MockDataProvider $data, #[Autowire('%kernel.environment%')] string $environment): Response
    {
        if ('dev' !== $environment && 'test' !== $environment) {
            throw $this->createNotFoundException();
        }

        return $this->render('styleguide/index.html.twig', ['authors' => $data->authors(), 'articles' => $data->articles()]);
    }
}
