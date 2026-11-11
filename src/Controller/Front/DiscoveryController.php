<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Mock\MockCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscoveryController extends AbstractController
{
    public function __construct(private readonly MockCatalog $catalog)
    {
    }

    #[Route('/authors', name: 'front_authors', defaults: ['kind' => 'author'], methods: ['GET'])]
    #[Route('/categories', name: 'front_categories', defaults: ['kind' => 'category'], methods: ['GET'])]
    #[Route('/tags', name: 'front_tags', defaults: ['kind' => 'tag'], methods: ['GET'])]
    public function index(string $kind): Response
    {
        return $this->render('front/discovery/index.html.twig', array_merge($this->catalog->layout(), ['kind' => $kind]));
    }

    #[Route('/authors/{slug}', name: 'front_author', defaults: ['kind' => 'author'], methods: ['GET'])]
    #[Route('/categories/{slug}', name: 'front_category', defaults: ['kind' => 'category'], methods: ['GET'])]
    #[Route('/tags/{slug}', name: 'front_tag', defaults: ['kind' => 'tag'], methods: ['GET'])]
    public function show(string $kind, string $slug, Request $request): Response
    {
        $collection = $this->catalog->collection($kind, $slug) ?? throw $this->createNotFoundException();
        $articles = $this->catalog->articles([$kind => $slug]);
        $pages = max(1, (int) ceil(count($articles) / 9));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));

        return $this->render('front/discovery/show.html.twig', array_merge($this->catalog->layout(), [
            'kind' => $kind, 'collection' => $collection, 'articles' => array_slice($articles, ($page - 1) * 9, 9),
            'total' => count($articles), 'page' => $page, 'pages' => $pages,
        ]));
    }
}
