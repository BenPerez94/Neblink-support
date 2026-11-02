<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102140953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE devis (
              id INT AUTO_INCREMENT NOT NULL,
              numero VARCHAR(30) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              total DOUBLE PRECISION NOT NULL,
              lignes JSON NOT NULL,
              project_id INT NOT NULL,
              document_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_8B27C52BF55AE19E (numero),
              INDEX IDX_8B27C52B166D1F9C (project_id),
              UNIQUE INDEX UNIQ_8B27C52BC33F7837 (document_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tarif (
              id INT AUTO_INCREMENT NOT NULL,
              categorie VARCHAR(80) NOT NULL,
              libelle VARCHAR(255) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              prix DOUBLE PRECISION NOT NULL,
              unite VARCHAR(20) DEFAULT NULL,
              obligatoire TINYINT DEFAULT 0 NOT NULL,
              actif TINYINT DEFAULT 1 NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              devis
            ADD
              CONSTRAINT FK_8B27C52B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              devis
            ADD
              CONSTRAINT FK_8B27C52BC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE
            SET
              NULL
        SQL);

        // Grille tarifaire initiale : [catégorie, libellé, description, prix, unité, obligatoire]
        $socle = 'Socle du site';
        $pages = 'Pages';
        $courantes = 'Fonctionnalités courantes';
        $lourdes = 'Fonctionnalités lourdes';
        $services = 'Services autour du site';

        $tarifs = [
            [$socle, 'Socle du site', 'Page d\'accueil, design responsive, mise en ligne, SEO de base, mentions légales et 2 tours de modifications.', 390, null, 1],
            [$pages, 'Page simple', 'Texte + images', 90, null, 0],
            [$pages, 'Page élaborée', 'Plusieurs sections, mise en page sur-mesure', 150, null, 0],
            [$pages, 'Rédaction du contenu d\'une page', null, 50, null, 0],
            [$courantes, 'Formulaire de contact', 'Anti-spam, envoi par mail', 80, null, 0],
            [$courantes, 'Formulaire avancé', 'Demande de devis, pièce jointe, champs conditionnels', 150, null, 0],
            [$courantes, 'Carrousel / slider', null, 50, null, 0],
            [$courantes, 'Galerie photos avec agrandissement', null, 70, null, 0],
            [$courantes, 'Carte Google Maps', null, 30, null, 0],
            [$courantes, 'FAQ en accordéon', null, 40, null, 0],
            [$courantes, 'Bandeau cookies RGPD', null, 50, null, 0],
            [$courantes, 'Liens réseaux sociaux', null, 0, null, 0],
            [$courantes, 'Flux Instagram intégré', null, 80, null, 0],
            [$courantes, 'Affichage des avis Google', null, 80, null, 0],
            [$courantes, 'Prise de rendez-vous en ligne', 'Calendly ou équivalent', 60, null, 0],
            [$courantes, 'Inscription newsletter', 'Brevo, Mailchimp', 80, null, 0],
            [$courantes, 'Animations au scroll', null, 80, null, 0],
            [$lourdes, 'Blog / actualités avec back-office', null, 400, null, 0],
            [$lourdes, 'Espace de modification des textes et images', 'Le client modifie lui-même ses textes et images', 300, null, 0],
            [$lourdes, 'Version dans une langue supplémentaire', null, 200, 'langue', 0],
            [$lourdes, 'Mini-boutique', 'Jusqu\'à 10 produits, paiement Stripe', 500, null, 0],
            [$services, 'Création ou optimisation de la fiche Google Business', null, 60, null, 0],
            [$services, 'Logo simple', null, 150, null, 0],
            [$services, 'Modifications au-delà des 2 tours inclus', null, 40, 'h', 0],
        ];

        foreach ($tarifs as $position => [$categorie, $libelle, $description, $prix, $unite, $obligatoire]) {
            $this->addSql(
                'INSERT INTO tarif (categorie, libelle, description, prix, unite, obligatoire, actif, position) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                [$categorie, $libelle, $description, $prix, $unite, $obligatoire, $position]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis DROP FOREIGN KEY FK_8B27C52B166D1F9C');
        $this->addSql('ALTER TABLE devis DROP FOREIGN KEY FK_8B27C52BC33F7837');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE tarif');
    }
}
