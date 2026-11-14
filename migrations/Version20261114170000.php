<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Archive obsolete legacy newsletter campaign counters before removing them from the active schema.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('newsletter_campaign');
        $obsolete = ['recipient_count', 'sent_count', 'failed_count', 'skipped_count', 'uncertain_count'];
        $present = array_filter($obsolete, $table->hasColumn(...));
        if ([] === $present) {
            return;
        }
        $this->abortIf(count($present) !== count($obsolete), 'Partial legacy campaign counter schema; inspect before upgrading.');
        if (!$schema->hasTable('newsletter_campaign_legacy_archive') && !$this->archiveExists()) {
            $this->addSql('CREATE TABLE newsletter_campaign_legacy_archive LIKE newsletter_campaign');
        }
        // Preserve pre-ledger-upgrade snapshots; fill only missing campaign records.
        $this->addSql('INSERT INTO newsletter_campaign_legacy_archive SELECT campaign.* FROM newsletter_campaign campaign WHERE NOT EXISTS (SELECT 1 FROM newsletter_campaign_legacy_archive archive WHERE archive.id = campaign.id)');
        $this->addSql('ALTER TABLE newsletter_campaign DROP recipient_count, DROP sent_count, DROP failed_count, DROP skipped_count, DROP uncertain_count');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('newsletter_campaign_legacy_archive') || $this->archiveExists()) {
            $this->throwIrreversibleMigrationException('Restore the reviewed pre-upgrade backup to recover legacy campaign counters.');
        }
    }

    private function archiveExists(): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', ['newsletter_campaign_legacy_archive']);
    }
}
