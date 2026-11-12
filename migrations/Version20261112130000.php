<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a MySQL/MariaDB FULLTEXT index for public article search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD FULLTEXT INDEX ft_article_search (title, excerpt, content)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP INDEX ft_article_search');
    }
}
