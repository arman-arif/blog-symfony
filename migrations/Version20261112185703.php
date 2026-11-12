<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112185703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advertisement ADD target_url VARCHAR(2048) DEFAULT NULL, ADD after_paragraph INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD is_sponsored TINYINT NOT NULL, ADD affiliate_links TINYINT NOT NULL, ADD is_premium TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advertisement DROP target_url, DROP after_paragraph');
        $this->addSql('ALTER TABLE article DROP is_sponsored, DROP affiliate_links, DROP is_premium');
    }
}
