<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\ArticleRevision;
use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\ArticleRevisionRepository;
use App\Repository\CategoryRepository;
use App\Repository\RedirectRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\Article\ArticleService;
use App\Service\Article\TaxonomyService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Cache\ItemInterface;

final class ArticleSaveRegressionTest extends FrontTestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function rejectedEdits(): iterable
    {
        yield 'invalid workflow edge' => ['ROLE_EDITOR', 'pending_review', 'Rejected workflow title'];
        yield 'forbidden unpublish' => ['ROLE_AUTHOR', 'unpublished', 'Rejected unpublish title'];
        yield 'invalid form' => ['ROLE_EDITOR', 'published', 'bad'];
    }

    #[DataProvider('rejectedEdits')]
    public function testRejectedEditsCannotPersistOnALaterFlush(string $role, string $status, string $title): void
    {
        $client = self::createClient();
        $this->seed();
        $actor = $this->user($role);
        $article = $this->story($actor);
        $article->getSeo()->setTitle('Original SEO title');
        $category = self::getContainer()->get(CategoryRepository::class)->findAll()[0];
        $tag = self::getContainer()->get(TagRepository::class)->findAll()[0];
        $article->addCategory($category)->addTag($tag);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $id = $article->getId();
        $slug = $article->getSlug();
        $categoryId = $category->getId();
        $tagId = $tag->getId();
        $client->loginUser($actor);
        $crawler = $client->request('GET', '/admin/articles/'.$id.'/edit');
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[title]'] = $title;
        $form['article_form[slug]'] = 'rejected-new-slug';
        $form['article_form[content]'] = '<p>Rejected but sufficiently long body.</p>';
        $form['article_form[status]'] = $status;
        $form['article_form[categories]'] = [];
        $form['article_form[tags]'] = [];
        $form['article_form[seo][title]'] = 'Rejected SEO title';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $reloaded = self::getContainer()->get(ArticleRepository::class)->find($id);
        self::assertSame('Original atomic story', $reloaded->getTitle());
        self::assertSame('<p>Original atomic content.</p>', $reloaded->getContent());
        self::assertSame($slug, $reloaded->getSlug());
        self::assertSame(ArticleStatus::Published, $reloaded->getStatus());
        self::assertSame('Original SEO title', $reloaded->getSeo()->getTitle());
        self::assertSame([$categoryId], $reloaded->getCategories()->map(static fn (Category $value): ?int => $value->getId())->toArray());
        self::assertSame([$tagId], $reloaded->getTags()->map(static fn (Tag $value): ?int => $value->getId())->toArray());
        self::assertCount(0, self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($reloaded));
        self::assertNull(self::getContainer()->get(RedirectRepository::class)->findOneBySourcePath('/blog/'.$slug));
    }

    /** @return iterable<string, array{string}> */
    public static function assignedStatuses(): iterable
    {
        yield 'published' => ['published'];
        yield 'scheduled' => ['scheduled'];
    }

    #[DataProvider('assignedStatuses')]
    public function testEditorCreatesAssignedArticlesUsingTheirOwnPermissionsAndAttribution(string $status): void
    {
        $client = self::createClient();
        $this->seed();
        $editor = $this->user('ROLE_EDITOR');
        $author = $this->user('ROLE_AUTHOR');
        $editorId = $editor->getId();
        $authorId = $author->getId();
        $client->loginUser($editor);
        $crawler = $client->request('GET', '/admin/articles/new');
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[title]'] = 'Assigned atomic '.$status.' story';
        $form['article_form[content]'] = '<p>Assigned story with sufficient content.</p>';
        $form['article_form[status]'] = $status;
        $form['article_form[author]'] = (string) $authorId;
        if ('scheduled' === $status) {
            $form['article_form[scheduledAt]'] = (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i');
        }
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Assigned atomic '.$status.' story']);
        self::assertNotNull($article);
        self::assertSame($authorId, $article->getAuthor()->getId());
        self::assertSame($status, $article->getStatus()->value);
        $revisions = self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article);
        self::assertCount(1, $revisions);
        self::assertSame($editorId, $revisions[0]->getCreatedBy()->getId());
        self::assertFalse($revisions[0]->isAutosave());
        self::assertSame($article->getContent(), $revisions[0]->getContent());
    }

    public function testCreationAndSubsequentSavesRetainRestorableOriginalContent(): void
    {
        self::createClient();
        $this->seed();
        $editor = $this->user('ROLE_EDITOR');
        $service = self::getContainer()->get(ArticleService::class);
        $article = (new Article())->setTitle('Initial snapshot story')->setContent('<p>Original snapshot content.</p>');
        $service->create($article, $editor);
        $initial = self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article)[0];
        $article->setContent('<p>Updated snapshot content.</p>');
        $service->update($article, $editor);
        $service->restoreRevision($article, $initial, $editor);
        self::assertSame('<p>Original snapshot content.</p>', $article->getContent());
        self::assertCount(3, self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article));
        $copy = $service->duplicate($article, $editor);
        self::assertCount(1, self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($copy));
    }

    public function testRejectedCreationAndServiceUpdateDoNotLeavePendingWrites(): void
    {
        self::createClient();
        $this->seed();
        $author = $this->user('ROLE_AUTHOR');
        $service = self::getContainer()->get(ArticleService::class);
        $rejected = (new Article())->setTitle('Forbidden published creation')->setContent('<p>Forbidden creation body.</p>');
        try {
            $service->create($rejected, $author, ArticleStatus::Published);
            self::fail('An Author must not publish.');
        } catch (AccessDeniedException) {
        }
        $article = $this->story($author);
        $id = $article->getId();
        $article->setTitle('Forbidden service update')->setContent('<p>Forbidden service content.</p>');
        try {
            $service->update($article, $author, ArticleStatus::Unpublished);
            self::fail('An Author must not unpublish.');
        } catch (AccessDeniedException) {
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $repository = self::getContainer()->get(ArticleRepository::class);
        self::assertNull($repository->findOneBy(['title' => 'Forbidden published creation']));
        self::assertSame('Original atomic story', $repository->find($id)->getTitle());
        self::assertSame('<p>Original atomic content.</p>', $repository->find($id)->getContent());
    }

    public function testManualSaveCommitsStatusContentRevisionRedirectAndCacheInvalidationTogether(): void
    {
        self::createClient();
        $this->seed();
        $editor = $this->user('ROLE_EDITOR');
        $article = $this->story($editor);
        $oldSlug = $article->getSlug();
        $cache = self::getContainer()->get('cache.content');
        $cache->get('atomic-save-marker', static function (ItemInterface $item): string {
            $item->tag(['homepage']);

            return 'before';
        });
        $details = clone $article;
        $details->setTitle('Committed atomic title')->setSlug('committed-atomic-slug')->setContent('<p>Committed atomic content.</p>');
        self::getContainer()->get(ArticleService::class)->update($article, $editor, ArticleStatus::Unpublished, details: $details);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $article = self::getContainer()->get(ArticleRepository::class)->find($article->getId());
        self::assertSame('Committed atomic title', $article->getTitle());
        self::assertSame(ArticleStatus::Unpublished, $article->getStatus());
        $revisions = self::getContainer()->get(ArticleRevisionRepository::class)->findForArticle($article);
        self::assertCount(1, $revisions);
        self::assertSame($article->getContent(), $revisions[0]->getContent());
        self::assertSame('/blog/committed-atomic-slug', self::getContainer()->get(RedirectRepository::class)->findOneBySourcePath('/blog/'.$oldSlug)->getTargetUrl());
        self::assertSame('after', $cache->get('atomic-save-marker', static fn (): string => 'after'));
    }

    /** @return iterable<string, array{string}> */
    public static function failedOperations(): iterable
    {
        yield 'create' => ['create'];
        yield 'update' => ['update'];
        yield 'restore revision' => ['restore'];
        yield 'status change' => ['status'];
    }

    #[DataProvider('failedOperations')]
    public function testFailureDuringRevisionInsertRollsBackTheEntireSave(string $operation): void
    {
        self::createClient();
        $this->seed();
        $editor = $this->user('ROLE_EDITOR');
        $article = $this->story($editor);
        $id = $article->getId();
        $oldSlug = $article->getSlug();
        $service = self::getContainer()->get(ArticleService::class);
        $revision = (new ArticleRevision())->setArticle($article)->setTitle('Restored atomic title')->setContent('<p>Restored atomic content.</p>');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($revision);
        $em->flush();
        $connection = $em->getConnection();
        $revisionCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM article_revision');
        $articleCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM article');
        $cache = self::getContainer()->get('cache.content');
        $cache->get('failed-save-marker', static function (ItemInterface $item): string {
            $item->tag(['homepage']);

            return 'unchanged';
        });
        $em->getEventManager()->addEventListener(['postPersist'], new class {
            public function postPersist(PostPersistEventArgs $args): void
            {
                if ($args->getObject() instanceof ArticleRevision) {
                    throw new \RuntimeException('Injected revision persistence failure');
                }
            }
        });
        try {
            if ('create' === $operation) {
                $service->create((new Article())->setTitle('Failed new atomic story')->setContent('<p>Failed new atomic content.</p>'), $editor, ArticleStatus::Published);
            } elseif ('restore' === $operation) {
                $service->restoreRevision($article, $revision, $editor);
            } elseif ('status' === $operation) {
                $service->applyStatus($article, ArticleStatus::Unpublished, $editor);
            } else {
                $details = clone $article;
                $details->setTitle('Failed atomic update')->setSlug('failed-atomic-update')->setContent('<p>Failed atomic update content.</p>');
                $service->update($article, $editor, ArticleStatus::Unpublished, details: $details);
            }
            self::fail('Expected injected persistence failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected revision persistence failure', $exception->getMessage());
        }
        self::assertFalse($em->isOpen(), 'A failed flush must discard the UnitOfWork, not leave dirty entities for a later flush.');
        self::assertSame($articleCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM article'));
        self::assertSame($revisionCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM article_revision'));
        self::assertSame('Original atomic story', $connection->fetchOne('SELECT title FROM article WHERE id = ?', [$id]));
        self::assertSame('published', $connection->fetchOne('SELECT status FROM article WHERE id = ?', [$id]));
        self::assertSame($oldSlug, $connection->fetchOne('SELECT slug FROM article WHERE id = ?', [$id]));
        self::assertFalse($connection->fetchOne('SELECT id FROM redirect WHERE source_path = ?', ['/blog/'.$oldSlug]));
        self::assertSame('unchanged', $cache->get('failed-save-marker', static fn (): string => 'invalidated'));
    }

    public function testTaxonomySlugsPreserveManualValuesResolveCollisionsAndSurviveRenaming(): void
    {
        self::createClient();
        $this->seed();
        $service = self::getContainer()->get(TaxonomyService::class);
        $tag = $service->saveTag((new Tag())->setName('Manual tag')->setSlug('kept-tag'));
        $category = $service->saveCategory((new Category())->setName('Manual category')->setSlug('kept-category'));
        self::assertSame('kept-tag', $tag->getSlug());
        self::assertSame('kept-category', $category->getSlug());
        self::assertSame('kept-tag-2', $service->saveTag((new Tag())->setName('Collision tag')->setSlug('kept-tag'))->getSlug());
        self::assertSame('kept-category-2', $service->saveCategory((new Category())->setName('Collision category')->setSlug('kept-category'))->getSlug());
        $tag->setName('Renamed tag');
        $category->setName('Renamed category');
        self::assertSame('kept-tag', $service->saveTag($tag)->getSlug());
        self::assertSame('kept-category', $service->saveCategory($category)->getSlug());
        self::assertSame('generated-tag', $service->saveTag((new Tag())->setName('Generated tag'))->getSlug());
        self::assertSame('generated-category', $service->saveCategory((new Category())->setName('Generated category'))->getSlug());
    }

    private function user(string $role): User
    {
        foreach (self::getContainer()->get(UserRepository::class)->findAll() as $user) {
            if ($user->hasRoleCode($role)) {
                return $user;
            }
        }
        throw new \LogicException('Missing fixture role: '.$role);
    }

    private function story(User $author): Article
    {
        $article = (new Article())->setAuthor($author)->setTitle('Original atomic story')->setContent('<p>Original atomic content.</p>')
            ->setStatus(ArticleStatus::Published)->setPublishedAt(new \DateTimeImmutable('-1 minute'));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($article);
        $em->flush();

        return $article;
    }
}
