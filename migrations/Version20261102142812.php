<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102142812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // La mise en ligne devient une ligne facultative à part (50 €), retirée de la description du socle.
        $this->addSql(
            "UPDATE tarif SET description = ? WHERE libelle = 'Socle du site' AND obligatoire = 1",
            ["Page d'accueil, design responsive, SEO de base, mentions légales et 2 tours de modifications."]
        );
        $this->addSql(
            "INSERT INTO tarif (categorie, libelle, description, prix, unite, obligatoire, actif, position) SELECT ?, ?, NULL, 50, NULL, 0, 1, COALESCE(MAX(position), 0) + 1 FROM tarif",
            ['Services autour du site', 'Mise en ligne']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tarif WHERE libelle = 'Mise en ligne'");
        $this->addSql(
            "UPDATE tarif SET description = ? WHERE libelle = 'Socle du site' AND obligatoire = 1",
            ["Page d'accueil, design responsive, mise en ligne, SEO de base, mentions légales et 2 tours de modifications."]
        );
    }
}
