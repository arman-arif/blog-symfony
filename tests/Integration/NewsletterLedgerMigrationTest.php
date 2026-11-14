<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

require_once dirname(__DIR__, 2).'/migrations/Version20261114132536.php';
require_once dirname(__DIR__, 2).'/migrations/Version20261114170000.php';

final class NewsletterLedgerMigrationTest extends KernelTestCase
{
    /** @return iterable<string, array{bool}> */
    public static function installations(): iterable
    {
        yield 'fresh installation' => [false];
        yield 'populated legacy upgrade' => [true];
    }

    #[DataProvider('installations')]
    public function testMigrationExecutesAndPreservesLegacyData(bool $legacy): void
    {
        self::bootKernel();
        // Separate, unwrapped connection: MySQL DDL must not commit DAMA's test transaction.
        $connection = DriverManager::getConnection(self::getContainer()->get(Connection::class)->getParams());
        $suffix = bin2hex(random_bytes(6));
        $names = [];
        foreach (['newsletter_delivery_legacy_archive', 'newsletter_campaign_legacy_archive', 'newsletter_delivery', 'newsletter_campaign', 'newsletter_subscriber'] as $name) {
            $names[$name] = $name.'_'.$suffix;
        }
        $rewrite = static fn (string $sql): string => strtr($sql, $names + ['FK_EA832A7AF639F774' => 'FK_campaign_'.$suffix, 'FK_EA832A7A7808B1AD' => 'FK_subscriber_'.$suffix]);
        try {
            $connection->executeStatement('CREATE TABLE '.$names['newsletter_campaign'].' LIKE newsletter_campaign');
            $connection->executeStatement('CREATE TABLE '.$names['newsletter_subscriber'].' LIKE newsletter_subscriber');
            $schema = new Schema();
            if ($legacy) {
                $connection->executeStatement($rewrite('ALTER TABLE newsletter_campaign ADD recipient_count INT NOT NULL DEFAULT 99, ADD sent_count INT NOT NULL DEFAULT 98, ADD failed_count INT NOT NULL DEFAULT 1, ADD skipped_count INT NOT NULL DEFAULT 0, ADD uncertain_count INT NOT NULL DEFAULT 0'));
                $ddl = 'CREATE TABLE newsletter_delivery (subscriber_id INT NOT NULL, email VARCHAR(180) NOT NULL, consent_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, claimed_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, campaign_id INT NOT NULL, UNIQUE INDEX uniq_newsletter_recipient (campaign_id, subscriber_id), INDEX idx_newsletter_pending (campaign_id, status, subscriber_id), INDEX idx_newsletter_claim (status, claimed_at), INDEX IDX_EA832A7AF639F774 (campaign_id), CONSTRAINT FK_EA832A7AF639F774 FOREIGN KEY (campaign_id) REFERENCES newsletter_campaign (id) ON DELETE CASCADE)';
                $connection->executeStatement($rewrite($ddl));
                $table = $connection->createSchemaManager()->introspectTable($names['newsletter_delivery']);
                $schema = new Schema([new Table('newsletter_delivery', $table->getColumns(), $table->getIndexes())]);
                $connection->executeStatement($rewrite("INSERT INTO newsletter_campaign (id, subject, html, status, stats, created_at, updated_at) VALUES (1, 'Preserve me', '<p>Original</p>', 'sending', '{\"sent\":99,\"failed\":0}', NOW(), NOW())"));
                foreach (['sent', 'failed', 'skipped', 'pending', 'claimed', 'unknown'] as $id => $status) {
                    $connection->executeStatement($rewrite('INSERT INTO newsletter_delivery (subscriber_id, email, consent_hash, status, claimed_at, completed_at, created_at, updated_at, campaign_id) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW(), NOW(), 1)'), [$id + 1, 'legacy'.$id.'@example.test', str_repeat('a', 64), $status]);
                }
                $before = $connection->fetchAllAssociative('SELECT * FROM '.$names['newsletter_delivery'].' ORDER BY id');
            }
            $migration = new \DoctrineMigrations\Version20261114132536($connection, new NullLogger());
            $migration->up($schema);
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($rewrite($query->getStatement()), $query->getParameters());
            }
            $current = $connection->createSchemaManager()->introspectTable($names['newsletter_delivery']);
            self::assertTrue($current->hasColumn('recipient_id'));
            self::assertFalse($current->hasColumn('email'));
            self::assertFalse($current->getColumn('subscriber_id')->getNotnull());
            self::assertSame(['campaign_id', 'recipient_id'], $current->getIndex('uniq_newsletter_recipient')->getColumns());
            if (!$legacy) {
                $cleanup = new \DoctrineMigrations\Version20261114170000($connection, new NullLogger());
                $cleanup->up(new Schema([new Table('newsletter_campaign')]));
                self::assertSame([], $cleanup->getSql());
                $cleanup->down(new Schema());
            }
            if ($legacy) {
                self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM '.$names['newsletter_delivery_legacy_archive'].' ORDER BY id'));
                self::assertSame(['sent', 'failed', 'skipped', 'uncertain', 'uncertain', 'uncertain'], $connection->fetchFirstColumn('SELECT status FROM '.$names['newsletter_delivery'].' ORDER BY id'));
                self::assertSame([1, 2, 3, 4, 5, 6], array_map(intval(...), $connection->fetchFirstColumn('SELECT recipient_id FROM '.$names['newsletter_delivery'].' ORDER BY id')));
                self::assertSame(6, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$names['newsletter_delivery'].' WHERE subscriber_id IS NULL'));
                $stats = json_decode((string) $connection->fetchOne('SELECT stats FROM '.$names['newsletter_campaign']), true, flags: JSON_THROW_ON_ERROR);
                self::assertSame(['total' => 6, 'sent' => 1, 'failed' => 1, 'skipped' => 1, 'uncertain' => 3, 'pending' => 0, 'sending' => 0], $stats);
                self::assertSame('sending', $connection->fetchOne('SELECT status FROM '.$names['newsletter_campaign_legacy_archive']));
                self::assertSame('failed', $connection->fetchOne('SELECT status FROM '.$names['newsletter_campaign']));
                $campaignTable = $connection->createSchemaManager()->introspectTable($names['newsletter_campaign']);
                $archiveTable = $connection->createSchemaManager()->introspectTable($names['newsletter_campaign_legacy_archive']);
                $cleanup = new \DoctrineMigrations\Version20261114170000($connection, new NullLogger());
                $cleanup->up(new Schema([
                    new Table('newsletter_campaign', $campaignTable->getColumns()),
                    new Table('newsletter_campaign_legacy_archive', $archiveTable->getColumns()),
                ]));
                foreach ($cleanup->getSql() as $query) {
                    $connection->executeStatement($rewrite($query->getStatement()), $query->getParameters());
                }
                self::assertFalse($connection->createSchemaManager()->introspectTable($names['newsletter_campaign'])->hasColumn('recipient_count'));
                self::assertSame(99, (int) $connection->fetchOne('SELECT recipient_count FROM '.$names['newsletter_campaign_legacy_archive']));
                self::assertSame('sending', $connection->fetchOne('SELECT status FROM '.$names['newsletter_campaign_legacy_archive']));
                $schema = new Schema([new Table('newsletter_delivery_legacy_archive')]);
                $this->expectException(AbortMigration::class);
                (new \DoctrineMigrations\Version20261114132536($connection, new NullLogger()))->down($schema);
            }
        } finally {
            foreach ($names as $name) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$name);
            }
            $connection->close();
        }
    }

    public function testUnknownSchemaIsRejectedBeforeAnyWrite(): void
    {
        self::bootKernel();
        $migration = new \DoctrineMigrations\Version20261114132536(self::getContainer()->get(Connection::class), new NullLogger());
        $schema = new Schema([new Table('newsletter_delivery')]);
        try {
            $migration->up($schema);
            self::fail('Unknown schemas must be rejected.');
        } catch (AbortMigration) {
            self::assertSame([], $migration->getSql());
        }
    }

    public function testArchiveGuardsDoNotDependOnFilteredSchemaListings(): void
    {
        self::bootKernel();
        $real = self::getContainer()->get(Connection::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($real->createSchemaManager());
        $connection->method('getDatabasePlatform')->willReturn($real->getDatabasePlatform());
        $connection->expects(self::exactly(4))->method('fetchOne')
            ->with('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', self::callback(static fn (array $parameters): bool => in_array($parameters, [['newsletter_delivery_legacy_archive'], ['newsletter_campaign_legacy_archive']], true)))
            ->willReturn(1);
        $ledger = new \DoctrineMigrations\Version20261114132536($connection, new NullLogger());
        $columns = array_map(static fn (string $name): Column => new Column($name, 'string'), ['campaign_id', 'claimed_at', 'completed_at', 'consent_hash', 'created_at', 'email', 'id', 'status', 'subscriber_id', 'updated_at']);
        try {
            $ledger->up(new Schema([new Table('newsletter_delivery', $columns)]));
            self::fail('A hidden archive must block conversion.');
        } catch (AbortMigration) {
            self::assertSame([], $ledger->getSql());
        }
        try {
            $ledger->down(new Schema());
            self::fail('A hidden archive must block rollback.');
        } catch (AbortMigration) {
            self::assertSame([], $ledger->getSql());
        }
        $cleanup = new \DoctrineMigrations\Version20261114170000($connection, new NullLogger());
        $columns = array_map(static fn (string $name): Column => new Column($name, 'integer'), ['recipient_count', 'sent_count', 'failed_count', 'skipped_count', 'uncertain_count']);
        $cleanup->up(new Schema([new Table('newsletter_campaign', $columns)]));
        self::assertCount(2, $cleanup->getSql());
        self::assertStringStartsWith('INSERT INTO', $cleanup->getSql()[0]->getStatement());
        $this->expectException(IrreversibleMigration::class);
        $cleanup->down(new Schema());
    }
}
