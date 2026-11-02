<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102145218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE echeance (
              id INT AUTO_INCREMENT NOT NULL,
              libelle VARCHAR(120) NOT NULL,
              montant DOUBLE PRECISION NOT NULL,
              date_echeance DATE DEFAULT NULL,
              payee_le DATETIME DEFAULT NULL,
              position INT DEFAULT 0 NOT NULL,
              devis_id INT NOT NULL,
              INDEX IDX_40D9893B41DEFADA (devis_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              echeance
            ADD
              CONSTRAINT FK_40D9893B41DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE devis ADD statut VARCHAR(20) DEFAULT \'genere\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE echeance DROP FOREIGN KEY FK_40D9893B41DEFADA');
        $this->addSql('DROP TABLE echeance');
        $this->addSql('ALTER TABLE devis DROP statut');
    }
}
