<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830165506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shortlist table (offer ids stored as JSON)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shortlist (visitor_session_id VARCHAR(64) NOT NULL, offer_ids JSON NOT NULL COMMENT \'(DC2Type:json)\', PRIMARY KEY(visitor_session_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE shortlist');
    }
}
