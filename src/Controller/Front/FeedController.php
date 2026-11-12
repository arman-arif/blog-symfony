<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FeedController extends AbstractController
{
    #[Route('/feed.xml', name: 'front_feed', methods: ['GET'])]
    #[Route('/feed/category/{slug}.xml', name: 'front_category_feed', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function feed(PublicCatalog $catalog, CategoryRepository $categories, ?string $slug = null): Response
    {
        $filters = [];
        $title = 'Fieldnotes';
        if (null !== $slug) {
            $category = $categories->findOneBySlug($slug) ?? throw $this->createNotFoundException();
            $filters['category'] = $slug;
            $title = 'Fieldnotes · '.$category->getName();
        }

        $result = $catalog->articles($filters, 1, 20);
        $response = $this->render('front/feed.xml.twig', [
            'articles' => $result['items'],
            'title' => $title,
            'description' => 'Independent stories about thoughtful work, culture and everyday life.',
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }
}
