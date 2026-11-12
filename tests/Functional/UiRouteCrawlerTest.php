<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

final class UiRouteCrawlerTest extends WebTestCase
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
        $paths = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($name, 'front_') && !str_starts_with($name, 'admin_') && !in_array($name, ['styleguide', '_preview_error'], true)) {
                continue;
            }
            $parameters = [];
            foreach ($route->compile()->getPathVariables() as $variable) {
                $parameters[$variable] = match ($variable) {
                    'id' => 1, 'revision' => 11, 'slug' => $slugs[$name], 'code' => 404, '_format' => 'html',
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
        foreach ($paths as $path => $status) {
            if (str_starts_with($path, '/admin') && 200 === $status) {
                foreach (['loading', 'empty', 'error'] as $state) {
                    $paths[$path.'?state='.$state] = 200;
                }
            }
        }
        foreach (array_keys($data->pages()) as $slug) {
            $paths[$router->generate('front_page', ['slug' => $slug])] = 200;
        }
        foreach (['authors' => 'front_author', 'categories' => 'front_category', 'tags' => 'front_tag'] as $method => $name) {
            foreach ($data->$method() as $record) {
                $paths[$router->generate($name, ['slug' => $record['slug']])] = 200;
            }
        }
        foreach ($data->articles() as $article) {
            if ('published' === $article['status']) {
                $paths[$router->generate('front_article', ['slug' => $article['slug']])] = 200;
            }
        }
        $links = [];
        foreach ($paths as $path => $status) {
            $crawler = $client->request('GET', $path);
            self::assertResponseStatusCodeSame($status, $path);
            self::assertSelectorCount(1, 'main#main-content');
            self::assertSelectorExists('main h1');
            self::assertSelectorExists('a[href="#main-content"]');
            self::assertSelectorNotExists('form form');
            self::assertSelectorNotExists('img:not([alt])');
            self::assertSelectorNotExists('iframe[src^="https://"]');
            if (str_starts_with($path, '/admin')) {
                self::assertSelectorExists('nav[aria-label="Breadcrumb"] a.underline');
            }
            if (str_ends_with($path, '?state=loading')) {
                self::assertSelectorExists('main [role="status"] .skeleton');
            } elseif (str_ends_with($path, '?state=empty')) {
                self::assertSelectorExists('main .text-center h2');
            } elseif (str_ends_with($path, '?state=error')) {
                self::assertSelectorExists('main [role="alert"]');
                self::assertSelectorTextContains('main', 'Try again');
            }
            foreach ($crawler->filter('form[data-component~="validate"]') as $form) {
                self::assertInstanceOf(\DOMElement::class, $form);
                $fields = (new Crawler($form))->filter('fieldset[disabled][data-preview-fields]');
                self::assertCount(1, $fields, 'Unsafe no-JavaScript preview form on '.$path);
                self::assertNotEmpty($form->getAttribute('data-success-message'));
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
                } elseif (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
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
