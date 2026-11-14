<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $this->addSql('CREATE TABLE newsletter_delivery (recipient_id INT NOT NULL, status VARCHAR(20) NOT NULL, attempted_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, campaign_id INT NOT NULL, subscriber_id INT DEFAULT NULL, INDEX idx_newsletter_pending (campaign_id, status, recipient_id), UNIQUE INDEX uniq_newsletter_recipient (campaign_id, recipient_id), INDEX IDX_EA832A7AF639F774 (campaign_id), INDEX IDX_EA832A7A7808B1AD (subscriber_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE newsletter_delivery ADD CONSTRAINT FK_EA832A7AF639F774 FOREIGN KEY (campaign_id) REFERENCES newsletter_campaign (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE newsletter_delivery ADD CONSTRAINT FK_EA832A7A7808B1AD FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscriber (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_delivery DROP FOREIGN KEY FK_EA832A7AF639F774');
        $this->addSql('ALTER TABLE newsletter_delivery DROP FOREIGN KEY FK_EA832A7A7808B1AD');
        $this->addSql('DROP TABLE newsletter_delivery');
    }
}
