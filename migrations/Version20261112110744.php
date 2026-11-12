<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112110744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advertisement (name VARCHAR(150) NOT NULL, placement VARCHAR(30) NOT NULL, type VARCHAR(20) NOT NULL, content LONGTEXT DEFAULT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, is_active TINYINT NOT NULL, priority INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, image_id INT DEFAULT NULL, INDEX idx_advertisement_active (is_active, placement), INDEX IDX_C95F6AEE3DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (title VARCHAR(255) NOT NULL, subtitle VARCHAR(500) DEFAULT NULL, slug VARCHAR(280) NOT NULL, excerpt LONGTEXT DEFAULT NULL, content LONGTEXT NOT NULL, status VARCHAR(30) NOT NULL, visibility VARCHAR(20) NOT NULL, published_at DATETIME DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL, allow_comments TINYINT NOT NULL, reading_time INT NOT NULL, view_count INT NOT NULL, like_count INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description LONGTEXT DEFAULT NULL, seo_focus_keyword VARCHAR(100) DEFAULT NULL, seo_canonical_url VARCHAR(2048) DEFAULT NULL, seo_noindex TINYINT NOT NULL, seo_og_title VARCHAR(255) DEFAULT NULL, seo_og_description LONGTEXT DEFAULT NULL, seo_og_image_url VARCHAR(2048) DEFAULT NULL, author_id INT NOT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_23A0E66989D9B62 (slug), INDEX idx_article_status_published_at (status, published_at), INDEX idx_article_author (author_id), INDEX IDX_23A0E663569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_category (article_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_53A4EDAA7294869C (article_id), INDEX IDX_53A4EDAA12469DE2 (category_id), PRIMARY KEY (article_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_tag (article_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_919694F97294869C (article_id), INDEX IDX_919694F9BAD26311 (tag_id), PRIMARY KEY (article_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_revision (title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, is_autosave TINYINT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX idx_article_revision_article (article_id, created_at), INDEX IDX_388BE1A77294869C (article_id), INDEX IDX_388BE1A7B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (name VARCHAR(150) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, lft INT NOT NULL, rgt INT NOT NULL, lvl INT NOT NULL, root INT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description LONGTEXT DEFAULT NULL, seo_focus_keyword VARCHAR(100) DEFAULT NULL, seo_canonical_url VARCHAR(2048) DEFAULT NULL, seo_noindex TINYINT NOT NULL, seo_og_title VARCHAR(255) DEFAULT NULL, seo_og_description LONGTEXT DEFAULT NULL, seo_og_image_url VARCHAR(2048) DEFAULT NULL, image_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX IDX_64C19C13DA5256D (image_id), INDEX IDX_64C19C1727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media (filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(150) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, alt VARCHAR(500) DEFAULT NULL, caption VARCHAR(500) DEFAULT NULL, storage_key VARCHAR(500) NOT NULL, variants JSON NOT NULL, id INT AUTO_INCREMENT NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, uploaded_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6A2CA10C111795A5 (storage_key), INDEX IDX_6A2CA10CA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu (name VARCHAR(150) NOT NULL, location VARCHAR(20) NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7D053A935E9E89CB (location), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_item (label VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL, target VARCHAR(2048) NOT NULL, position INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, menu_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX idx_menu_item_position (menu_id, parent_id, position), INDEX IDX_D754D550CCD7E912 (menu_id), INDEX IDX_D754D550727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page (title VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, published_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description LONGTEXT DEFAULT NULL, seo_focus_keyword VARCHAR(100) DEFAULT NULL, seo_canonical_url VARCHAR(2048) DEFAULT NULL, seo_noindex TINYINT NOT NULL, seo_og_title VARCHAR(255) DEFAULT NULL, seo_og_description LONGTEXT DEFAULT NULL, seo_og_image_url VARCHAR(2048) DEFAULT NULL, author_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_140AB620989D9B62 (slug), INDEX IDX_140AB620F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description LONGTEXT DEFAULT NULL, seo_focus_keyword VARCHAR(100) DEFAULT NULL, seo_canonical_url VARCHAR(2048) DEFAULT NULL, seo_noindex TINYINT NOT NULL, seo_og_title VARCHAR(255) DEFAULT NULL, seo_og_description LONGTEXT DEFAULT NULL, seo_og_image_url VARCHAR(2048) DEFAULT NULL, UNIQUE INDEX UNIQ_389B783989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE advertisement ADD CONSTRAINT FK_C95F6AEE3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E663569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_53A4EDAA7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_category ADD CONSTRAINT FK_53A4EDAA12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_tag ADD CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_revision ADD CONSTRAINT FK_388BE1A77294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_revision ADD CONSTRAINT FK_388BE1A7B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C13DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10CA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_user ADD avatar_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E986383B10 FOREIGN KEY (avatar_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88BDF3E986383B10 ON app_user (avatar_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advertisement DROP FOREIGN KEY FK_C95F6AEE3DA5256D');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F675F31B');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E663569D950');
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_53A4EDAA7294869C');
        $this->addSql('ALTER TABLE article_category DROP FOREIGN KEY FK_53A4EDAA12469DE2');
        $this->addSql('ALTER TABLE article_tag DROP FOREIGN KEY FK_919694F97294869C');
        $this->addSql('ALTER TABLE article_tag DROP FOREIGN KEY FK_919694F9BAD26311');
        $this->addSql('ALTER TABLE article_revision DROP FOREIGN KEY FK_388BE1A77294869C');
        $this->addSql('ALTER TABLE article_revision DROP FOREIGN KEY FK_388BE1A7B03A8386');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C13DA5256D');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10CA2B28FE8');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550CCD7E912');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550727ACA70');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620F675F31B');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E986383B10');
        $this->addSql('DROP INDEX IDX_88BDF3E986383B10 ON app_user');
        $this->addSql('ALTER TABLE app_user DROP avatar_id');
        $this->addSql('DROP TABLE advertisement');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_category');
        $this->addSql('DROP TABLE article_tag');
        $this->addSql('DROP TABLE article_revision');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE tag');
    }
}
