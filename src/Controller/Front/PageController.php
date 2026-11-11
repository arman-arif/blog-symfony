<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Mock\MockCatalog;
use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/{slug}', name: 'front_page', requirements: ['slug' => 'about|contact|privacy|terms|cookies'], methods: ['GET'])]
    public function show(string $slug, MockCatalog $catalog, MockDataProvider $data): Response
    {
        return $this->render('front/page/index.html.twig', array_merge($catalog->layout(), ['slug' => $slug, 'page' => $data->pages()[$slug]]));
    }
}
