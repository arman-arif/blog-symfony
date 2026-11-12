<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\TagFactory;
use Doctrine\ORM\EntityManagerInterface;

final class CategoryTagCmsTest extends AdminTestCase
{
    public function testCategoryCrudPreventsDeletingParentsWithChildren(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/admin/categories/new');
        $form = $crawler->selectButton('Save category')->form();
        $form['category_form[name]'] = 'Field Notes';
        $form['category_form[slug]'] = '';
        $form['category_form[description]'] = 'Slow observations.';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $categories = self::getContainer()->get(CategoryRepository::class);
        $parent = $categories->findOneBy(['name' => 'Field Notes']);
        self::assertNotNull($parent);

        $crawler = $client->request('GET', '/admin/categories/new');
        $form = $crawler->selectButton('Save category')->form();
        $form['category_form[name]'] = 'Field Notes';
        $form['category_form[slug]'] = '';
        $form['category_form[parent]'] = (string) $parent->getId();
        $client->submit($form);

        $all = $categories->findBy(['name' => 'Field Notes']);
        self::assertCount(2, $all);
        self::assertNotSame($all[0]->getSlug(), $all[1]->getSlug());
        self::assertSame($parent->getId(), $all[1]->getParent()?->getId());

        $token = $this->deleteToken($client, '/admin/categories');
        $client->request('POST', '/admin/categories/'.$parent->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'child categories');
        self::assertNotNull($categories->find($parent->getId()));
    }

    public function testTagCrudSearchAndAutocomplete(): void
    {
        $client = self::createClient();
        $existing = TagFactory::createOne(['name' => 'Typography']);

        $crawler = $client->request('GET', '/admin/tags/new');
        $form = $crawler->selectButton('Save tag')->form();
        $form['tag_form[name]'] = 'Typography';
        $form['tag_form[slug]'] = '';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $tags = self::getContainer()->get(TagRepository::class);
        $created = $tags->findOneBy(['name' => 'Typography'], ['id' => 'DESC']);
        self::assertNotNull($created);
        self::assertNotSame($existing->getSlug(), $created->getSlug());

        $crawler = $client->request('GET', '/admin/tags?q=Typo');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#tag-search');

        $client->request('GET', '/admin/tags/autocomplete?q=Typo');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertCount(2, $payload);

        $token = $this->deleteToken($client, '/admin/tags');
        $client->request('POST', '/admin/tags/'.$created->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull(self::getContainer()->get(TagRepository::class)->find($created->getId()));
    }

    public function testTagQuickCreateIsIdempotentAndProtected(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/admin/tags');
        $token = (string) $crawler->filter('[data-tag-quick-create-token]')->attr('data-tag-quick-create-token');

        $client->request('POST', '/admin/tags/quick-create', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => $token,
        ], json_encode(['name' => 'Fresh topic']));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Fresh topic', $created['name']);

        $client->request('POST', '/admin/tags/quick-create', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => $token,
        ], json_encode(['name' => 'Fresh topic']));
        self::assertResponseStatusCodeSame(201);
        self::assertSame($created['id'], json_decode((string) $client->getResponse()->getContent(), true)['id']);

        $client->request('POST', '/admin/tags/quick-create', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => 'wrong',
        ], json_encode(['name' => 'Nope']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testCategoryEditUpdatesSlugAndSeo(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Old name']);

        $crawler = $client->request('GET', '/admin/categories/'.$category->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save category')->form();
        $form['category_form[name]'] = 'A better name';
        $form['category_form[slug]'] = 'a-better-name';
        $form['category_form[seo][title]'] = 'A better category';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = self::getContainer()->get(CategoryRepository::class)->find($category->getId());
        self::assertSame('a-better-name', $reloaded?->getSlug());
        self::assertSame('A better category', $reloaded?->getSeo()->getTitle());
    }

    private function deleteToken(object $client, string $path): string
    {
        $crawler = $client->request('GET', $path);
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->first()->attr('value');

        return (string) $token;
    }
}
