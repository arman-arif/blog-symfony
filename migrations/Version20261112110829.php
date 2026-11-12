<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112110829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_bookmark (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_article_bookmark_user (article_id, user_id), INDEX IDX_8F8A66767294869C (article_id), INDEX IDX_8F8A6676A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_daily_stat (stat_day DATE NOT NULL, views INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, UNIQUE INDEX uniq_article_daily_stat_day (article_id, stat_day), INDEX IDX_586731357294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_article_like_user (article_id, user_id), INDEX IDX_1C21C7B27294869C (article_id), INDEX IDX_1C21C7B2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_view (viewed_at DATETIME NOT NULL, visitor_hash VARCHAR(64) NOT NULL, referrer VARCHAR(2048) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX idx_article_view_article_viewed_at (article_id, viewed_at), INDEX idx_article_view_viewed_at (viewed_at), INDEX IDX_4EBF2C8F7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (guest_name VARCHAR(150) DEFAULT NULL, guest_email VARCHAR(180) DEFAULT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, ip_hash VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, report_count INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, parent_id INT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX idx_comment_article_status (article_id, status, created_at), INDEX IDX_9474526C7294869C (article_id), INDEX IDX_9474526C727ACA70 (parent_id), INDEX IDX_9474526CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE newsletter_campaign (subject VARCHAR(255) NOT NULL, html LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, scheduled_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, stats JSON NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE newsletter_subscriber (email VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, token VARCHAR(64) NOT NULL, confirmed_at DATETIME DEFAULT NULL, unsubscribed_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_401562C3E7927C74 (email), UNIQUE INDEX UNIQ_401562C35F37A13B (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article_bookmark ADD CONSTRAINT FK_8F8A66767294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_bookmark ADD CONSTRAINT FK_8F8A6676A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_daily_stat ADD CONSTRAINT FK_586731357294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_like ADD CONSTRAINT FK_1C21C7B27294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_like ADD CONSTRAINT FK_1C21C7B2A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_view ADD CONSTRAINT FK_4EBF2C8F7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_bookmark DROP FOREIGN KEY FK_8F8A66767294869C');
        $this->addSql('ALTER TABLE article_bookmark DROP FOREIGN KEY FK_8F8A6676A76ED395');
        $this->addSql('ALTER TABLE article_daily_stat DROP FOREIGN KEY FK_586731357294869C');
        $this->addSql('ALTER TABLE article_like DROP FOREIGN KEY FK_1C21C7B27294869C');
        $this->addSql('ALTER TABLE article_like DROP FOREIGN KEY FK_1C21C7B2A76ED395');
        $this->addSql('ALTER TABLE article_view DROP FOREIGN KEY FK_4EBF2C8F7294869C');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7294869C');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('DROP TABLE article_bookmark');
        $this->addSql('DROP TABLE article_daily_stat');
        $this->addSql('DROP TABLE article_like');
        $this->addSql('DROP TABLE article_view');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE newsletter_campaign');
        $this->addSql('DROP TABLE newsletter_subscriber');
    }
}
