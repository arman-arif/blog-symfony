<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;

final class PerformanceTest extends FrontTestCase
{
    public function testPublicListingAndArticleQueriesStayBounded(): void
    {
        $client = self::createClient();
        $this->seed();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $holder);

        $holder->reset();
        $client->request('GET', '/blog');
        self::assertResponseIsSuccessful();
        $listingQueries = $this->queryCount($holder);
        self::assertLessThanOrEqual(30, $listingQueries, 'Public listing query count: '.$listingQueries);

        $holder->reset();
        $client->request('GET', '/blog/the-quiet-power-of-making-things-with-intention');
        self::assertResponseIsSuccessful();
        $articleQueries = $this->queryCount($holder);
        self::assertLessThanOrEqual(40, $articleQueries, 'Article page query count: '.$articleQueries);
    }

    public function testAdminListsAvoidUnboundedPerRowQueries(): void
    {
        $client = self::createClient();
        $this->seed();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(BacktraceDebugDataHolder::class, $holder);
        $admin = self::getContainer()->get(\App\Repository\UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        foreach (['/admin/articles', '/admin/users', '/admin/comments', '/admin/media'] as $path) {
            $holder->reset();
            $client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
            $count = $this->queryCount($holder);
            self::assertLessThanOrEqual(45, $count, $path.' query count: '.$count);
        }
    }

    private function queryCount(BacktraceDebugDataHolder $holder): int
    {
        $data = $holder->getData();

        return count($data['default'] ?? []);
    }
}
