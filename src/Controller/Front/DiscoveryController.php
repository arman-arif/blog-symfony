<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscoveryController extends AbstractController
{
    #[Route('/authors', name: 'front_authors', defaults: ['kind' => 'author'], methods: ['GET'])]
    #[Route('/categories', name: 'front_categories', defaults: ['kind' => 'category'], methods: ['GET'])]
    #[Route('/tags', name: 'front_tags', defaults: ['kind' => 'tag'], methods: ['GET'])]
    public function index(string $kind, PublicCatalog $catalog): Response
    {
        return $this->render('front/discovery/index.html.twig', array_merge($catalog->layout(), ['kind' => $kind]));
    }

    #[Route('/authors/{slug}', name: 'front_author', defaults: ['kind' => 'author'], methods: ['GET'])]
    #[Route('/categories/{slug}', name: 'front_category', defaults: ['kind' => 'category'], methods: ['GET'])]
    #[Route('/tags/{slug}', name: 'front_tag', defaults: ['kind' => 'tag'], methods: ['GET'])]
    public function show(string $kind, string $slug, Request $request, PublicCatalog $catalog): Response
    {
        $collection = $catalog->collection($kind, $slug) ?? throw $this->createNotFoundException();
        $result = $catalog->articles([$kind => $slug], $request->query->getInt('page', 1), 9);

        return $this->render('front/discovery/show.html.twig', array_merge($catalog->layout(), [
            'kind' => $kind, 'collection' => $collection, 'articles' => $result['items'],
            'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages'],
        ]));
    }
}
