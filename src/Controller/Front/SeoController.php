<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\Settings\SettingsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class SeoController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'cache.content')]
        private readonly TagAwareCacheInterface $cache,
        private readonly SettingsServiceInterface $settings,
    ) {
    }

    #[Route('/sitemap.xml', name: 'front_sitemap', methods: ['GET'])]
    public function index(): Response
    {
        $routes = ['front_sitemap_articles', 'front_sitemap_categories', 'front_sitemap_tags', 'front_sitemap_authors', 'front_sitemap_pages'];
        $urls = array_map(fn (string $route): string => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL), $routes);

        return $this->xml('front/sitemap_index.xml.twig', ['sitemaps' => $urls]);
    }

    #[Route('/sitemap-articles.xml', name: 'front_sitemap_articles', methods: ['GET'])]
    public function articles(ArticleRepository $articles): Response
    {
        $urls = $this->cache->get('sitemap.articles.v1', function (ItemInterface $item) use ($articles): array {
            $item->expiresAfter(3600);
            $item->tag(['articles', 'sitemap']);

            return array_map(fn ($article): array => [
                'loc' => $this->generateUrl('front_article', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $article->getUpdatedAt(),
            ], $articles->findForSitemap());
        });

        return $this->xml('front/sitemap_urls.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-categories.xml', name: 'front_sitemap_categories', methods: ['GET'])]
    public function categories(CategoryRepository $categories): Response
    {
        $urls = $this->cache->get('sitemap.categories.v1', function (ItemInterface $item) use ($categories): array {
            $item->expiresAfter(3600);
            $item->tag(['sitemap']);

            return array_map(fn ($category): array => [
                'loc' => $this->generateUrl('front_category', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $category->getUpdatedAt(),
            ], array_filter($categories->findTree(), static fn ($category): bool => !$category->getSeo()->isNoindex()));
        });

        return $this->xml('front/sitemap_urls.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-tags.xml', name: 'front_sitemap_tags', methods: ['GET'])]
    public function tags(TagRepository $tags): Response
    {
        $urls = $this->cache->get('sitemap.tags.v1', function (ItemInterface $item) use ($tags): array {
            $item->expiresAfter(3600);
            $item->tag(['sitemap']);

            return array_map(fn ($tag): array => [
                'loc' => $this->generateUrl('front_tag', ['slug' => $tag->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $tag->getUpdatedAt(),
            ], array_filter($tags->findBy([], ['name' => 'ASC']), static fn ($tag): bool => !$tag->getSeo()->isNoindex()));
        });

        return $this->xml('front/sitemap_urls.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-authors.xml', name: 'front_sitemap_authors', methods: ['GET'])]
    public function authors(UserRepository $users, ArticleRepository $articles): Response
    {
        $urls = $this->cache->get('sitemap.authors.v1', function (ItemInterface $item) use ($users, $articles): array {
            $item->expiresAfter(3600);
            $item->tag(['sitemap']);
            $counts = $articles->publishedCountsByAuthor();

            return array_values(array_map(fn ($author): array => [
                'loc' => $this->generateUrl('front_author', ['slug' => $author->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $author->getUpdatedAt(),
            ], array_filter($users->findBy(['isActive' => true], ['displayName' => 'ASC']), static fn ($author): bool => ($counts[$author->getId()] ?? 0) > 0)));
        });

        return $this->xml('front/sitemap_urls.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-pages.xml', name: 'front_sitemap_pages', methods: ['GET'])]
    public function pages(PageRepository $pages): Response
    {
        $urls = $this->cache->get('sitemap.pages.v1', function (ItemInterface $item) use ($pages): array {
            $item->expiresAfter(3600);
            $item->tag(['sitemap']);

            return array_map(fn ($page): array => [
                'loc' => $this->generateUrl('front_page', ['slug' => $page->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $page->getUpdatedAt(),
            ], $pages->findForSitemap());
        });

        return $this->xml('front/sitemap_urls.xml.twig', ['urls' => $urls]);
    }

    #[Route('/robots.txt', name: 'front_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $content = rtrim($this->settings->getString('seo.robots'));
        if (!str_contains($content, 'Sitemap:')) {
            $content .= "\nSitemap: ".$this->generateUrl('front_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return new Response($content."\n", Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @param array<string, mixed> $context */
    private function xml(string $template, array $context): Response
    {
        return $this->render($template, $context, new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
