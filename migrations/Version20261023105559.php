<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023105559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Blog : contenu sans balises HTML pour la recherche';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD contenu_texte LONGTEXT NOT NULL');
        // Articles existants : on retire les balises (les suivants passent par ContentProcessor)
        $this->addSql("UPDATE article SET contenu_texte = TRIM(REGEXP_REPLACE(REGEXP_REPLACE(contenu, '<[^>]+>', ' '), '[[:space:]]+', ' '))");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP contenu_texte');
    }
}
