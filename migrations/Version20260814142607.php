<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814142607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD password_reset_token VARCHAR(255) DEFAULT NULL, ADD password_reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74404556B7BA4B6 ON client (password_reset_token)');
        $this->addSql('ALTER TABLE user ADD password_reset_token VARCHAR(255) DEFAULT NULL, ADD password_reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496B7BA4B6 ON user (password_reset_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_C74404556B7BA4B6 ON client');
        $this->addSql('ALTER TABLE client DROP password_reset_token, DROP password_reset_token_expires_at');
        $this->addSql('DROP INDEX UNIQ_8D93D6496B7BA4B6 ON user');
        $this->addSql('ALTER TABLE user DROP password_reset_token, DROP password_reset_token_expires_at');
    }
}
