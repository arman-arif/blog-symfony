<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PageRepository;
use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/{slug}', name: 'front_page', requirements: ['slug' => 'about|contact|privacy|terms|cookies'], methods: ['GET'])]
    public function show(string $slug, PageRepository $pages, PublicCatalog $catalog): Response
    {
        $page = $pages->findPublishedBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('front/page/index.html.twig', array_merge($catalog->layout(), ['slug' => $slug, 'page' => $page]));
    }
}
