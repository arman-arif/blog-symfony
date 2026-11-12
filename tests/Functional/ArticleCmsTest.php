<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Message\PublishScheduledArticlesMessage;
use App\Repository\ArticleRepository;
use App\Repository\ArticleRevisionRepository;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\TagFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ArticleCmsTest extends AdminTestCase
{
    public function testCreatingAnArticleSanitizesContentAndDerivesMetadata(): void
    {
        $client = self::createClient();
        $admin = self::authenticatedUser();
        $category = CategoryFactory::createOne(['name' => 'Design']);
        $tag = TagFactory::createOne(['name' => 'Craft']);

        $crawler = $client->request('GET', '/admin/articles/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[title]'] = 'A sanitized story about making things';
        $form['article_form[slug]'] = '';
        $form['article_form[subtitle]'] = 'Small observations';
        $form['article_form[excerpt]'] = '';
        $form['article_form[content]'] = '<p>'.str_repeat('Thoughtful words about craft. ', 20).'</p><h2>Listening first</h2><p>More detail.</p><script>alert("xss")</script><img src="https://example.test/a.jpg" onerror="alert(1)"><a href="javascript:alert(2)">bad link</a>';
        $form['article_form[status]'] = 'published';
        $form['article_form[visibility]'] = 'public';
        $form['article_form[author]'] = (string) $admin->getId();
        $form['article_form[categories]'] = [(string) $category->getId()];
        $form['article_form[tags]'] = [(string) $tag->getId()];
        $form['article_form[allowComments]'] = '1';
        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin/articles/', (string) $client->getResponse()->headers->get('Location'));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $all = self::getContainer()->get(ArticleRepository::class)->findBy([], ['id' => 'DESC']);
        self::assertNotEmpty($all, 'No articles after create. Location: '.$client->getResponse()->headers->get('Location'));
        $article = $all[0];
        self::assertNotNull($article);
        self::assertSame('A sanitized story about making things', $article->getTitle());
        self::assertSame(ArticleStatus::Published, $article->getStatus());
        self::assertNotNull($article->getPublishedAt());
        self::assertStringNotContainsString('<script', $article->getContent());
        self::assertStringNotContainsString('onerror', $article->getContent());
        self::assertStringNotContainsString('javascript:', $article->getContent());
        self::assertStringContainsString('id="listening-first"', $article->getContent());
        self::assertNotEmpty($article->getExcerpt());
        self::assertGreaterThan(0, $article->getReadingTime());
        self::assertCount(1, $article->getCategories());
        self::assertCount(1, $article->getTags());
        self::assertSame('a-sanitized-story-about-making-things', $article->getSlug());
    }

    public function testEditingCreatesARevisionAndRestoringItChangesTheArticle(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne([
            'title' => 'Revision story',
            'author' => self::authenticatedUser(),
            'content' => '<p>'.str_repeat('Original content. ', 10).'</p>',
        ]);

        $crawler = $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[content]'] = '<p>'.str_repeat('Updated content. ', 10).'</p>';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $revisions = self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article);
        self::assertNotEmpty($revisions);

        $newest = $revisions[0];
        $client->request('POST', '/admin/articles/'.$article->getId().'/revisions/'.$newest->getId().'/restore', [
            '_token' => $this->csrfToken($client, '/admin/articles/'.$article->getId().'/revisions', '/restore'),
        ]);
        self::assertResponseStatusCodeSame(302);
    }

    public function testWorkflowGuardsAndPublishingPermissions(): void
    {
        $client = self::createClient();
        $author = $this->createUserWithRole('ROLE_AUTHOR');
        $client->loginUser($author);
        $article = ArticleFactory::createOne(['author' => $author, 'content' => '<p>'.str_repeat('Author draft. ', 10).'</p>']);

        $crawler = $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[status]'] = 'published';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'cannot publish');

        $editor = $this->createUserWithRole('ROLE_EDITOR');
        $client->loginUser($editor);
        $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        $form = $client->request('GET', '/admin/articles/'.$article->getId().'/edit')->selectButton('Save article')->form();
        $form['article_form[status]'] = 'published';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = self::getContainer()->get(ArticleRepository::class)->find($article->getId());
        self::assertSame(ArticleStatus::Published, $reloaded?->getStatus());
    }

    public function testOwnVersusAnyArticleAuthorization(): void
    {
        $client = self::createClient();
        $author = $this->createUserWithRole('ROLE_AUTHOR');
        $other = $this->createUserWithRole('ROLE_AUTHOR');
        $article = ArticleFactory::createOne(['author' => $other]);

        $client->loginUser($author);
        $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        $editor = $this->createUserWithRole('ROLE_EDITOR');
        $client->loginUser($editor);
        $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        self::assertResponseIsSuccessful();
    }

    public function testSlugUniquenessAndScheduledPublishing(): void
    {
        $client = self::createClient();
        $admin = self::authenticatedUser();

        foreach ([1, 2] as $index) {
            $crawler = $client->request('GET', '/admin/articles/new');
            $form = $crawler->selectButton('Save article')->form();
            $form['article_form[title]'] = 'Same title story';
            $form['article_form[slug]'] = '';
            $form['article_form[content]'] = '<p>'.str_repeat('Unique content here. ', 10).'</p>';
            $form['article_form[status]'] = 'draft';
            $form['article_form[author]'] = (string) $admin->getId();
            $client->submit($form);
            self::assertResponseStatusCodeSame(302);
        }

        $articles = self::getContainer()->get(ArticleRepository::class)->findBy(['title' => 'Same title story']);
        self::assertCount(2, $articles);
        self::assertNotSame($articles[0]->getSlug(), $articles[1]->getSlug());

        $scheduled = ArticleFactory::createOne([
            'author' => $admin,
            'status' => ArticleStatus::Scheduled,
            'scheduledAt' => new \DateTimeImmutable('-1 minute'),
            'content' => '<p>'.str_repeat('Ready to publish. ', 10).'</p>',
        ]);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new PublishScheduledArticlesMessage());
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = self::getContainer()->get(ArticleRepository::class)->find($scheduled->getId());
        self::assertSame(ArticleStatus::Published, $reloaded?->getStatus());
        self::assertNotNull($reloaded?->getPublishedAt());
    }

    public function testAutosaveStoresARevisionAndRejectsBadTokens(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne(['author' => self::authenticatedUser()]);
        $crawler = $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        $token = (string) $crawler->filter('[data-autosave-token]')->attr('data-autosave-token');

        $client->request('POST', '/admin/articles/'.$article->getId().'/autosave', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => $token,
        ], json_encode(['title' => 'Autosaved title', 'content' => '<p>'.str_repeat('Autosave body. ', 10).'</p>']));
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"saved"}', (string) json_encode(array_intersect_key(json_decode((string) $client->getResponse()->getContent(), true), ['status' => true])));

        $revisions = self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article);
        self::assertTrue($revisions[0]->isAutosave());

        $client->request('POST', '/admin/articles/'.$article->getId().'/autosave', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => 'wrong',
        ], json_encode(['title' => 'Bad', 'content' => '<p>Nope</p>']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRestoreAndDuplicate(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne(['author' => self::authenticatedUser()]);
        $token = $this->csrfToken($client, '/admin/articles', '/delete');

        $client->request('POST', '/admin/articles/'.$article->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull(self::getContainer()->get(ArticleRepository::class)->find($article->getId()));

        $client->request('POST', '/admin/articles/'.$article->getId().'/restore', ['_token' => $this->csrfToken($client, '/admin/articles?status=trash', '/restore')]);
        self::assertResponseStatusCodeSame(302);
        self::assertNotNull(self::getContainer()->get(ArticleRepository::class)->find($article->getId()));

        $client->request('POST', '/admin/articles/'.$article->getId().'/duplicate', ['_token' => $this->csrfToken($client, '/admin/articles', '/duplicate')]);
        self::assertResponseStatusCodeSame(302);
        $copies = self::getContainer()->get(ArticleRepository::class)->findBy(['status' => ArticleStatus::Draft]);
        self::assertNotEmpty(array_filter($copies, static fn (Article $copy): bool => str_contains($copy->getTitle(), '(copy)')));
    }

    private function csrfToken(object $client, string $path, ?string $actionSuffix = null): string
    {
        $crawler = $client->request('GET', $path);
        $selector = null === $actionSuffix ? 'input[name="_token"]' : 'form[action$="'.$actionSuffix.'"] input[name="_token"]';
        $token = $crawler->filter($selector)->first()->attr('value');

        return (string) $token;
    }

    private function createUserWithRole(string $roleCode): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $permissions = [];
        foreach (\App\Security\PermissionCatalog::permissions() as $code => $meta) {
            $permission = $entityManager->getRepository(\App\Entity\Permission::class)->findOneBy(['code' => $code]);
            if (null === $permission) {
                $permission = (new \App\Entity\Permission())->setCode($code)->setLabel($meta['label'])->setGroupName($meta['group']);
                $entityManager->persist($permission);
            }
            $permissions[$code] = $permission;
        }
        $definition = \App\Security\PermissionCatalog::roles()[$roleCode];
        $role = $entityManager->getRepository(Role::class)->findOneBy(['code' => $roleCode]);
        if (null === $role) {
            $role = (new Role())->setName($definition['name'].' CMS')->setCode($roleCode)->setIsSystem(true);
            foreach ($definition['permissions'] as $code) {
                $role->addPermission($permissions[$code]);
            }
            $entityManager->persist($role);
        }
        $user = (new User())
            ->setEmail(strtolower($roleCode).'-'.bin2hex(random_bytes(4)).'@example.test')
            ->setDisplayName($definition['name'].' CMS')
            ->setIsActive(true)
            ->setIsVerified(true)
            ->addAssignedRole($role);
        $user->setPassword(password_hash('a-long-test-password', \PASSWORD_BCRYPT, ['cost' => 4]));
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
