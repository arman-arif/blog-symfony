<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Mock\MockCatalog;
use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'front_blog', methods: ['GET'])]
    public function index(Request $request, MockCatalog $catalog): Response
    {
        $filters = [];
        foreach (['q', 'category', 'tag', 'author', 'sort'] as $key) {
            $filters[$key] = $request->query->getString($key);
        }
        $all = $catalog->articles($filters);
        $pages = max(1, (int) ceil(count($all) / 9));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));

        return $this->render('front/blog/index.html.twig', array_merge($catalog->layout(), [
            'articles' => array_slice($all, ($page - 1) * 9, 9), 'total' => count($all),
            'page' => $page, 'pages' => $pages, 'filters' => $filters,
        ]));
    }

    #[Route('/blog/{slug}', name: 'front_article', methods: ['GET'])]
    public function show(string $slug, MockCatalog $catalog, MockDataProvider $data): Response
    {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $articles = $catalog->articles();
        $position = array_search($slug, array_column($articles, 'slug'), true);

        return $this->render('front/article/index.html.twig', array_merge($catalog->layout(), [
            'article' => $article, 'comments' => $data->comments(),
            'related' => array_slice(array_values(array_filter($catalog->articles(['category' => $article['categories'][0]['slug']]), static fn (array $other): bool => $other['slug'] !== $slug)), 0, 3),
            'previous' => false !== $position && $position > 0 ? $articles[$position - 1] : null,
            'next' => false !== $position ? ($articles[$position + 1] ?? null) : null,
        ]));
    }
}
