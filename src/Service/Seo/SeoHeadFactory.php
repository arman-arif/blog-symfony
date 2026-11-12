<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Page;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\Media\MediaUrlResolver;
use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Resolves canonical metadata, social cards and schema.org JSON-LD for every public page. */
final readonly class SeoHeadFactory
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private ArticleRepository $articles,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private UserRepository $users,
        private PageRepository $pages,
        private MediaUrlResolver $mediaUrls,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array<string, mixed> */
    public function forRequest(Request $request): array
    {
        $route = (string) $request->attributes->get('_route');
        $slug = (string) $request->attributes->get('slug', '');
        $page = max(1, $request->query->getInt('page', 1));

        return match ($route) {
            'front_article' => ($article = $this->articles->findOnePublishedBySlug($slug)) ? $this->forArticle($article) : $this->defaults($request),
            'front_category' => ($category = $this->categories->findOneBySlug($slug)) ? $this->forCollection('category', $category, $page) : $this->defaults($request),
            'front_tag' => ($tag = $this->tags->findOneBySlug($slug)) ? $this->forCollection('tag', $tag, $page) : $this->defaults($request),
            'front_author' => ($author = $this->users->findOneBySlug($slug)) ? $this->forCollection('author', $author, $page) : $this->defaults($request),
            'front_page', 'front_page_show' => ($pageEntity = $this->pages->findPublishedBySlug($slug)) ? $this->forPage($pageEntity) : $this->defaults($request),
            'front_home' => $this->forHome(),
            'front_blog', 'front_search' => $this->forListing($route, $page, $request->query->all()),
            default => $this->defaults($request),
        };
    }

    /** @return array<string, mixed> */
    public function forHome(): array
    {
        $name = $this->settings->getString('general.name');

        return $this->build(
            $name.' · '.$this->settings->getString('general.tagline'),
            $this->settings->getString('seo.description'),
            $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'website',
            null,
            null,
            null,
            null,
            [
                ['name' => 'Home', 'url' => $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            ],
        );
    }

    /** @return array<string, mixed> */
    public function forArticle(Article $article): array
    {
        $name = $this->settings->getString('general.name');
        $url = $this->urls->generate('front_article', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $canonical = $article->getSeo()->getCanonicalUrl() ?: $url;
        $description = $article->getSeo()->getDescription() ?: $article->getExcerpt() ?: $this->settings->getString('seo.description');
        $title = $article->getSeo()->getTitle() ?: $article->getTitle();
        $image = $article->getFeaturedImage();
        $crumbs = [
            ['name' => 'Home', 'url' => $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            ['name' => 'Journal', 'url' => $this->urls->generate('front_blog', [], UrlGeneratorInterface::ABSOLUTE_URL)],
        ];
        $category = $article->getCategories()->first();
        if ($category instanceof Category) {
            $crumbs[] = ['name' => $category->getName(), 'url' => $this->urls->generate('front_category', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)];
        }
        $crumbs[] = ['name' => $article->getTitle(), 'url' => $url];

        $meta = $this->build(
            $title === $name ? $title : $title.' · '.$name,
            $description,
            $canonical,
            'article',
            $image,
            $article->getPublishedAt(),
            $article->getUpdatedAt(),
            $article->getAuthor()->getDisplayName(),
            $crumbs,
            $article->getSeo()->isNoindex(),
            $article,
        );

        $meta['article'] = $article;

        return $meta;
    }

    /** @return array<string, mixed> */
    public function forCollection(string $kind, Category|Tag|User $entity, int $page = 1): array
    {
        $name = $this->settings->getString('general.name');
        $title = $entity instanceof User ? $entity->getDisplayName() : $entity->getName();
        $description = $entity instanceof User ? (string) $entity->getBio() : (string) $entity->getDescription();
        if ('' === trim($description)) {
            $description = $this->settings->getString('seo.description');
        }
        $route = 'front_'.$kind;
        $parameters = ['slug' => $entity->getSlug()];
        if ($page > 1) {
            $parameters['page'] = $page;
        }
        $url = $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        $noindex = $entity instanceof User ? false : $entity->getSeo()->isNoindex();
        $crumbs = [
            ['name' => 'Home', 'url' => $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            ['name' => ucfirst($kind).'s', 'url' => $this->urls->generate('front_'.('category' === $kind ? 'categories' : $kind.'s'), [], UrlGeneratorInterface::ABSOLUTE_URL)],
            ['name' => $title, 'url' => $url],
        ];

        $meta = $this->build($title.' · '.$name, $description, $url, 'website', null, null, null, $entity instanceof User ? $title : null, $crumbs, $noindex, null, $entity instanceof User ? $entity : null);
        if ('author' === $kind && $entity instanceof User) {
            $meta['author_entity'] = $entity;
        }

        return $meta;
    }

    /** @return array<string, mixed> */
    public function forPage(Page $page): array
    {
        $name = $this->settings->getString('general.name');
        $route = in_array($page->getSlug(), ['about', 'contact', 'privacy', 'terms', 'cookies'], true) ? 'front_page' : 'front_page_show';
        $url = $this->urls->generate($route, ['slug' => $page->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->build(
            ($page->getSeo()->getTitle() ?: $page->getTitle()).' · '.$name,
            $page->getSeo()->getDescription() ?: $this->settings->getString('seo.description'),
            $page->getSeo()->getCanonicalUrl() ?: $url,
            'website',
            null,
            $page->getPublishedAt(),
            $page->getUpdatedAt(),
            null,
            [
                ['name' => 'Home', 'url' => $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => $page->getTitle(), 'url' => $url],
            ],
            $page->getSeo()->isNoindex(),
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function forListing(string $route, int $page, array $filters): array
    {
        $name = $this->settings->getString('general.name');
        $search = 'front_search' === $route;
        $parameters = $page > 1 ? ['page' => $page] : [];
        $url = $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        $noindex = $search || [] !== array_filter($filters, static fn (mixed $value): bool => '' !== $value);

        return $this->build(
            $search ? 'Search · '.$name : 'The journal · '.$name,
            $search ? 'Search published Fieldnotes stories.' : 'Fresh perspectives on design, technology and the art of living well.',
            $url,
            'website',
            null,
            null,
            null,
            null,
            [
                ['name' => 'Home', 'url' => $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => $search ? 'Search' : 'Journal', 'url' => $url],
            ],
            $noindex,
        );
    }

    /** @return array<string, mixed> */
    private function defaults(Request $request): array
    {
        $name = $this->settings->getString('general.name');
        $url = $request->getSchemeAndHttpHost().$request->getPathInfo();

        $private = 1 === preg_match('#^/(admin|login|register|reset-password|2fa|profile|saved|_styleguide|_error|_maintenance)#', $request->getPathInfo());

        return $this->build($name, $this->settings->getString('seo.description'), $url, 'website', null, null, null, null, [], $private);
    }

    /**
     * @param list<array{name: string, url: string}> $crumbs
     *
     * @return array<string, mixed>
     */
    private function build(
        string $title,
        string $description,
        string $canonical,
        string $type,
        ?\App\Entity\Media $image,
        ?\DateTimeImmutable $publishedAt,
        ?\DateTimeImmutable $modifiedAt,
        ?string $author,
        array $crumbs,
        bool $noindex = false,
        ?Article $article = null,
        ?User $authorEntity = null,
    ): array {
        $siteName = $this->settings->getString('general.name');
        $home = $this->urls->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $graph = [
            ['@type' => 'Organization', 'name' => $siteName, 'url' => $home],
            [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $home,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $this->urls->generate('front_search', [], UrlGeneratorInterface::ABSOLUTE_URL).'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        $imageUrl = null;
        if (null !== $image) {
            $relative = $this->mediaUrls->url($image, 'large');
            $imageUrl = str_starts_with($relative, '/') ? $this->origin().$relative : $relative;
        }

        if (null !== $article) {
            $graph[] = [
                '@type' => 'BlogPosting',
                'headline' => $article->getTitle(),
                'description' => $description,
                'image' => $imageUrl,
                'datePublished' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
                'dateModified' => $article->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'author' => ['@type' => 'Person', 'name' => $article->getAuthor()->getDisplayName(), 'url' => $this->urls->generate('front_author', ['slug' => $article->getAuthor()->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)],
                'publisher' => ['@type' => 'Organization', 'name' => $siteName],
                'mainEntityOfPage' => $canonical,
                'wordCount' => max(1, str_word_count(strip_tags($article->getContent()))),
            ];
        }

        if (null !== $authorEntity) {
            $graph[] = [
                '@type' => 'Person',
                'name' => $authorEntity->getDisplayName(),
                'url' => $this->urls->generate('front_author', ['slug' => $authorEntity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        } elseif (null !== $author) {
            $graph[] = ['@type' => 'Person', 'name' => $author];
        }

        if ([] !== $crumbs) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(static fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ], $crumbs, array_keys($crumbs)),
            ];
        }

        return [
            'site_name' => $siteName,
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $noindex ? 'noindex,follow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
            'og_type' => $type,
            'image' => $image,
            'image_url' => $imageUrl,
            'published_at' => $publishedAt,
            'modified_at' => $modifiedAt,
            'author' => $author,
            'json_ld' => json_encode(
                ['@context' => 'https://schema.org', '@graph' => $graph],
                \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT,
            ),
            'ga_id' => $this->settings->getString('seo.ga_id'),
            'gsc_verification' => $this->settings->getString('seo.gsc_verification'),
        ];
    }

    private function origin(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getSchemeAndHttpHost() ?? '';
    }
}
