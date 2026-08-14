<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814093158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, password_setup_token VARCHAR(255) DEFAULT NULL, password_setup_token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_C7440455E7927C74 (email), UNIQUE INDEX UNIQ_C744045546600387 (password_setup_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, statut_paiement VARCHAR(20) DEFAULT NULL, fichier VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_D8698A76166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, client_id INT NOT NULL, INDEX IDX_2FB3D0EE19EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_step (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, termine TINYINT NOT NULL, position INT NOT NULL, project_id INT NOT NULL, INDEX IDX_7A283624166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE project_step ADD CONSTRAINT FK_7A283624166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76166D1F9C');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE19EB6921');
        $this->addSql('ALTER TABLE project_step DROP FOREIGN KEY FK_7A283624166D1F9C');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_step');
    }
}
