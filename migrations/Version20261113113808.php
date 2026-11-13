<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113113808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track completed physical cleanup on soft-deleted media tombstones.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media ADD files_deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media DROP files_deleted_at');
    }
}
