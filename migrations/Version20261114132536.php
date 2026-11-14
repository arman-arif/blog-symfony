<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114132536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist a unique delivery ledger for each newsletter campaign recipient.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('newsletter_delivery')) {
            $this->upgradeLegacyLedger($schema);

            return;
        }
        $this->addSql('CREATE TABLE newsletter_delivery (recipient_id INT NOT NULL, status VARCHAR(20) NOT NULL, attempted_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, campaign_id INT NOT NULL, subscriber_id INT DEFAULT NULL, INDEX idx_newsletter_pending (campaign_id, status, recipient_id), UNIQUE INDEX uniq_newsletter_recipient (campaign_id, recipient_id), INDEX IDX_EA832A7AF639F774 (campaign_id), INDEX IDX_EA832A7A7808B1AD (subscriber_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE newsletter_delivery ADD CONSTRAINT FK_EA832A7AF639F774 FOREIGN KEY (campaign_id) REFERENCES newsletter_campaign (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE newsletter_delivery ADD CONSTRAINT FK_EA832A7A7808B1AD FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscriber (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($schema->hasTable('newsletter_delivery_legacy_archive') || $this->archiveExists('newsletter_delivery_legacy_archive'), 'Legacy upgrade cannot be automatically reversed. Restore the reviewed pre-upgrade backup with workers stopped.');
        $this->abortIf(0 !== (int) $this->connection->fetchOne('SELECT COUNT(*) FROM newsletter_delivery'), 'Refusing to delete a populated delivery ledger.');
        $this->addSql('ALTER TABLE newsletter_delivery DROP FOREIGN KEY FK_EA832A7AF639F774');
        $this->addSql('ALTER TABLE newsletter_delivery DROP FOREIGN KEY FK_EA832A7A7808B1AD');
        $this->addSql('DROP TABLE newsletter_delivery');
    }

    private function upgradeLegacyLedger(Schema $schema): void
    {
        $table = $schema->getTable('newsletter_delivery');
        $columns = array_map(static fn (Column $column): string => $column->getName(), $table->getColumns());
        sort($columns);
        $expected = ['campaign_id', 'claimed_at', 'completed_at', 'consent_hash', 'created_at', 'email', 'id', 'status', 'subscriber_id', 'updated_at'];
        $this->abortIf($columns !== $expected, 'Unrecognized newsletter ledger schema; no changes made. Inspect and reconcile it first.');
        $this->abortIf($schema->hasTable('newsletter_delivery_legacy_archive') || $schema->hasTable('newsletter_campaign_legacy_archive') || $this->archiveExists('newsletter_delivery_legacy_archive') || $this->archiveExists('newsletter_campaign_legacy_archive'), 'An archive already exists. Inspect a possibly interrupted upgrade; do not overwrite it.');
        $this->abortIf(!$table->hasIndex('uniq_newsletter_recipient') || !$table->getIndex('uniq_newsletter_recipient')->isUnique() || $table->getIndex('uniq_newsletter_recipient')->getColumns() !== ['campaign_id', 'subscriber_id'], 'Legacy recipient uniqueness is not established.');

        // CREATE LIKE does not copy foreign keys: these immutable snapshots cannot be
        // cascade-deleted when application records are subsequently removed.
        $this->addSql('CREATE TABLE newsletter_delivery_legacy_archive LIKE newsletter_delivery');
        $this->addSql('INSERT INTO newsletter_delivery_legacy_archive SELECT * FROM newsletter_delivery');
        $this->addSql('CREATE TABLE newsletter_campaign_legacy_archive LIKE newsletter_campaign');
        $this->addSql('INSERT INTO newsletter_campaign_legacy_archive SELECT campaign.* FROM newsletter_campaign campaign WHERE EXISTS (SELECT 1 FROM newsletter_delivery legacy WHERE legacy.campaign_id = campaign.id)');
        $this->addSql('ALTER TABLE newsletter_delivery ADD recipient_id INT DEFAULT NULL, ADD attempted_at DATETIME DEFAULT NULL, ADD sent_at DATETIME DEFAULT NULL');
        // Unknown historical semantics must never cause an automatic SMTP replay.
        $this->addSql("UPDATE newsletter_delivery SET recipient_id = subscriber_id, attempted_at = claimed_at, sent_at = CASE WHEN status = 'sent' THEN completed_at ELSE NULL END, status = CASE WHEN status IN ('sent', 'failed', 'skipped') THEN status ELSE 'uncertain' END");
        if ($table->hasIndex('idx_newsletter_claim')) {
            $this->addSql('DROP INDEX idx_newsletter_claim ON newsletter_delivery');
        }
        $this->addSql('ALTER TABLE newsletter_delivery MODIFY recipient_id INT NOT NULL, MODIFY subscriber_id INT DEFAULT NULL, DROP email, DROP consent_hash, DROP claimed_at, DROP completed_at, DROP INDEX uniq_newsletter_recipient, DROP INDEX idx_newsletter_pending, ADD UNIQUE INDEX uniq_newsletter_recipient (campaign_id, recipient_id), ADD INDEX idx_newsletter_pending (campaign_id, status, recipient_id)');
        $this->addSql('UPDATE newsletter_delivery delivery LEFT JOIN newsletter_subscriber subscriber ON subscriber.id = delivery.subscriber_id SET delivery.subscriber_id = NULL WHERE subscriber.id IS NULL');
        if (!$table->hasIndex('IDX_EA832A7A7808B1AD')) {
            $this->addSql('CREATE INDEX IDX_EA832A7A7808B1AD ON newsletter_delivery (subscriber_id)');
        }
        $this->addSql('ALTER TABLE newsletter_delivery ADD CONSTRAINT FK_EA832A7A7808B1AD FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscriber (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            UPDATE newsletter_campaign campaign JOIN (
                SELECT campaign_id, COUNT(*) AS total, SUM(status = 'sent') AS sent,
                    SUM(status = 'failed') AS failed, SUM(status = 'skipped') AS skipped,
                    SUM(status = 'uncertain') AS uncertain, MAX(updated_at) AS completed_at
                FROM newsletter_delivery GROUP BY campaign_id
            ) ledger ON campaign.id = ledger.campaign_id
            SET campaign.stats = JSON_OBJECT('total', ledger.total, 'sent', ledger.sent, 'failed', ledger.failed,
                    'skipped', ledger.skipped, 'uncertain', ledger.uncertain, 'pending', 0, 'sending', 0),
                campaign.status = CASE WHEN ledger.failed + ledger.uncertain > 0 THEN 'failed' ELSE 'sent' END,
                campaign.sent_at = COALESCE(campaign.sent_at, ledger.completed_at)
            SQL);
    }

    private function archiveExists(string $name): bool
    {
        // Schema-manager table listings respect schema_filter; safety checks must not.
        return false !== $this->connection->fetchOne('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]);
    }
}
