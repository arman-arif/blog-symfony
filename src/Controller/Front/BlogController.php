<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Mock\MockDataProvider;
use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'front_blog', methods: ['GET'])]
    #[Route('/search', name: 'front_search', methods: ['GET'])]
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        $filters = [];
        foreach (['q', 'category', 'tag', 'author', 'sort'] as $key) {
            $filters[$key] = $request->query->getString($key);
        }
        $result = $catalog->articles($filters, $request->query->getInt('page', 1), 9);
        $isSearch = 'front_search' === $request->attributes->get('_route');

        return $this->render($isSearch ? 'front/search/index.html.twig' : 'front/blog/index.html.twig', array_merge($catalog->layout(), [
            'articles' => $result['items'], 'total' => $result['total'],
            'page' => $result['page'], 'pages' => $result['pages'], 'filters' => $filters,
            'listingRoute' => $isSearch ? 'front_search' : 'front_blog',
        ]));
    }

    #[Route('/blog/{slug}', name: 'front_article', methods: ['GET'])]
    public function show(string $slug, PublicCatalog $catalog, MockDataProvider $data): Response
    {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();

        return $this->render('front/article/index.html.twig', array_merge($catalog->layout(), [
            'article' => $article,
            'comments' => $data->comments(),
            'related' => $catalog->related($article),
            'previous' => $catalog->previous($article),
            'next' => $catalog->next($article),
        ]));
    }
}
