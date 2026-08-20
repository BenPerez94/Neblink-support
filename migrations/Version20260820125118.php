<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820125118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_step ADD description LONGTEXT DEFAULT NULL, ADD parent_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              project_step
            ADD
              CONSTRAINT FK_7A283624727ACA70 FOREIGN KEY (parent_id) REFERENCES project_step (id) ON DELETE CASCADE
        SQL);
        $this->addSql('CREATE INDEX IDX_7A283624727ACA70 ON project_step (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_step DROP FOREIGN KEY FK_7A283624727ACA70');
        $this->addSql('DROP INDEX IDX_7A283624727ACA70 ON project_step');
        $this->addSql('ALTER TABLE project_step DROP description, DROP parent_id');
    }
}
