<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ArticleLike;
use App\Entity\Permission;
use App\Entity\Role;
use App\Enum\ArticleStatus;
use App\Enum\CommentStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\CommentFactory;
use App\Tests\Factory\NewsletterSubscriberFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RepositoryIntegrationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testArticleRepositoryReturnsOnlyPublishedArticlesInThePast(): void
    {
        $author = UserFactory::createOne();
        ArticleFactory::createOne([
            'title' => 'Published story',
            'status' => ArticleStatus::Published,
            'publishedAt' => new \DateTimeImmutable('-2 days'),
            'author' => $author,
        ]);
        ArticleFactory::createOne([
            'title' => 'Draft story',
            'status' => ArticleStatus::Draft,
            'author' => $author,
        ]);
        ArticleFactory::createOne([
            'title' => 'Future story',
            'status' => ArticleStatus::Published,
            'publishedAt' => new \DateTimeImmutable('+2 days'),
            'author' => $author,
        ]);

        $repository = self::getContainer()->get(ArticleRepository::class);
        $published = $repository->findPublished();

        self::assertCount(1, $published);
        self::assertSame('Published story', $published[0]->getTitle());
    }

    public function testSoftDeletedArticlesDisappearFromQueries(): void
    {
        $article = ArticleFactory::createOne();
        $id = $article->getId();
        self::assertNotNull($id);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($article);
        $entityManager->flush();
        $entityManager->clear();

        self::assertNull(self::getContainer()->get(ArticleRepository::class)->find($id));
    }

    public function testDuplicateLikesAreRejectedByTheDatabase(): void
    {
        $article = ArticleFactory::createOne();
        $user = UserFactory::createOne();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $first = (new ArticleLike())->setArticle($article)->setUser($user);
        $entityManager->persist($first);
        $entityManager->flush();

        $second = (new ArticleLike())->setArticle($article)->setUser($user);
        $entityManager->persist($second);

        $this->expectException(UniqueConstraintViolationException::class);
        $entityManager->flush();
    }

    public function testApprovedCommentsAreReturnedAndPendingOnesAreNot(): void
    {
        $article = ArticleFactory::createOne();
        CommentFactory::createOne(['article' => $article, 'status' => CommentStatus::Approved]);
        CommentFactory::createOne(['article' => $article, 'status' => CommentStatus::Pending]);

        $comments = self::getContainer()->get(CommentRepository::class)
            ->findApprovedForArticle((int) $article->getId());

        self::assertCount(1, $comments);
        self::assertSame(CommentStatus::Approved, $comments[0]->getStatus());
    }

    public function testCategoryNestedTreeAssignsNestedSetBounds(): void
    {
        $root = CategoryFactory::createOne(['name' => 'Root category']);
        $child = CategoryFactory::createOne(['name' => 'Child category', 'parent' => $root]);

        self::assertSame($root->getId(), $child->getParent()?->getId());
        self::assertGreaterThan($root->getLft(), $child->getLft());
        self::assertSame(1, $child->getLvl());

        $roots = self::getContainer()->get(CategoryRepository::class)->findRoots();
        self::assertCount(1, $roots);
    }

    public function testUserRolesExposeSecurityCodesAndPermissions(): void
    {
        $permission = (new Permission())
            ->setCode('test.permission')
            ->setLabel('Test permission')
            ->setGroupName('Test');
        $role = (new Role())
            ->setName('Test role')
            ->setCode('ROLE_TEST')
            ->addPermission($permission);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($permission);
        $entityManager->persist($role);

        $user = UserFactory::createOne(['assignedRoles' => [$role]]);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_TEST', $user->getRoles());
        self::assertTrue($role->hasPermission('test.permission'));
    }

    public function testNewsletterSubscriberIsFoundByOpaqueToken(): void
    {
        $subscriber = NewsletterSubscriberFactory::createOne(['token' => 'a-test-token']);

        $found = self::getContainer()->get(NewsletterSubscriberRepository::class)
            ->findOneByToken('a-test-token');

        self::assertNotNull($found);
        self::assertSame($subscriber->getId(), $found->getId());
    }
}
