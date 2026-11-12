<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ArticleRevision;
use App\Mock\MockDataProvider;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\MediaFactory;
use App\Tests\Factory\TagFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

final class UiRouteCrawlerTest extends AdminTestCase
{
    public function testEveryUiRouteAndItsInternalLinks(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $data = new MockDataProvider();
        $slugs = [
            'front_article' => $data->articles()[0]['slug'], 'front_author' => $data->authors()[0]['slug'],
            'front_category' => $data->categories()[0]['slug'], 'front_tag' => $data->tags()[0]['slug'], 'front_page' => 'about',
        ];

        $article = ArticleFactory::createOne(['author' => self::authenticatedUser()]);
        $category = CategoryFactory::createOne(['name' => 'Crawler category']);
        $tag = TagFactory::createOne(['name' => 'Crawler tag']);
        $media = MediaFactory::createOne(['uploadedBy' => self::authenticatedUser()]);
        $revision = (new ArticleRevision())
            ->setArticle($article)
            ->setTitle($article->getTitle())
            ->setContent($article->getContent())
            ->setCreatedBy(self::authenticatedUser());
        self::getContainer()->get(EntityManagerInterface::class)->persist($revision);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $paths = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($name, 'front_') && !str_starts_with($name, 'admin_') && !in_array($name, ['styleguide', '_preview_error'], true)) {
                continue;
            }
            if ([] !== $route->getMethods() && !in_array('GET', $route->getMethods(), true)) {
                continue;
            }
            if (str_ends_with($name, '_autocomplete') || str_ends_with($name, '_picker')) {
                continue;
            }
            $parameters = [];
            foreach ($route->compile()->getPathVariables() as $variable) {
                $parameters[$variable] = match ($variable) {
                    'id' => match (true) {
                        str_starts_with($name, 'admin_category_') => $category->getId(),
                        str_starts_with($name, 'admin_tag_') => $tag->getId(),
                        str_starts_with($name, 'admin_article_') => $article->getId(),
                        str_starts_with($name, 'admin_media_') => $media->getId(),
                        default => 1,
                    },
                    'revision' => $revision->getId(),
                    'slug' => $slugs[$name],
                    'code' => 404,
                    '_format' => 'html',
                    default => throw new \LogicException('Add a crawler fixture for '.$name.': '.$variable),
                };
            }
            $paths[$router->generate($name, $parameters)] = match ($name) {
                'admin_permission_denied' => 403, '_preview_error' => 404, 'front_maintenance_preview' => 503,
                default => 200,
            };
        }
        self::assertGreaterThanOrEqual(58, count($paths), 'The crawler must not silently omit UI routes.');
        $paths['/_error/500'] = 500;
        $paths['/_error/418'] = 418;
        foreach (array_keys($data->pages()) as $slug) {
            $paths[$router->generate('front_page', ['slug' => $slug])] = 200;
        }
        foreach (['authors' => 'front_author', 'categories' => 'front_category', 'tags' => 'front_tag'] as $method => $name) {
            foreach ($data->$method() as $record) {
                $paths[$router->generate($name, ['slug' => $record['slug']])] = 200;
            }
        }
        foreach ($data->articles() as $fixture) {
            if ('published' === $fixture['status']) {
                $paths[$router->generate('front_article', ['slug' => $fixture['slug']])] = 200;
            }
        }
        $links = [];
        foreach ($paths as $path => $status) {
            $crawler = $client->request('GET', $path);
            self::assertResponseStatusCodeSame($status, $path);
            self::assertSelectorCount(1, 'main#main-content', 'Missing main landmark on '.$path);
            self::assertSelectorExists('main h1');
            self::assertSelectorExists('a[href="#main-content"]');
            self::assertSelectorNotExists('form form');
            self::assertSelectorNotExists('img:not([alt])');
            self::assertSelectorNotExists('iframe[src^="https://"]');
            if (str_starts_with($path, '/admin')) {
                self::assertSelectorExists('nav[aria-label="Breadcrumb"]');
            }
            foreach ($crawler->filter('fieldset[data-preview-fields][disabled]') as $fieldset) {
                self::assertInstanceOf(\DOMElement::class, $fieldset);
                $form = $fieldset->parentNode;
                self::assertInstanceOf(\DOMElement::class, $form, 'Preview fieldset outside a form on '.$path);
                self::assertNotEmpty($form->getAttribute('data-success-message'), 'Unsafe preview form on '.$path);
            }
            $ids = $crawler->filter('[id]')->each(static fn (Crawler $node): string => $node->attr('id') ?? '');
            self::assertSame($ids, array_values(array_unique($ids)), 'Duplicate IDs on '.$path);
            foreach ($crawler->filter('label[for]') as $label) {
                self::assertInstanceOf(\DOMElement::class, $label);
                self::assertContains($label->getAttribute('for'), $ids, 'Broken field label on '.$path);
            }
            foreach ($crawler->filter('[aria-controls], [aria-labelledby], [aria-describedby]') as $element) {
                self::assertInstanceOf(\DOMElement::class, $element);
                foreach (['aria-controls', 'aria-labelledby', 'aria-describedby'] as $attribute) {
                    foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                        if ('' !== $id) {
                            self::assertContains($id, $ids, 'Broken '.$attribute.' reference on '.$path);
                        }
                    }
                }
            }
            foreach ($crawler->filter('a[href]') as $link) {
                self::assertInstanceOf(\DOMElement::class, $link);
                $href = $link->getAttribute('href');
                self::assertFalse(str_starts_with(strtolower($href), 'javascript:'), $path);
                if (str_starts_with($href, '#') && '#' !== $href) {
                    self::assertContains(substr($href, 1), $ids, 'Broken fragment '.$href.' on '.$path);
                } elseif (str_starts_with($href, '/') && !str_starts_with($href, '//') && !str_starts_with($href, '/uploads/') && !str_starts_with($href, '/media/cache/') && !preg_match('#^/(admin/(articles|categories|tags)/\d+)#', $href)) {
                    $links[] = explode('#', $href)[0];
                }
            }
        }
        foreach (array_unique($links) as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken internal link: '.$path);
        }
    }
}
