<?php

namespace App\Command;

use App\Service\Blog\ArticleImporter;
use App\Service\Blog\ImportException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Importe un article Markdown en brouillon (même logique que la page d'import de l'admin).
 * Le format du fichier est décrit dans ArticleImporter.
 */
#[AsCommand(name: 'app:blog:import', description: 'Importe un article Markdown dans le blog (en brouillon)')]
class BlogImportCommand
{
    public function __construct(
        private ArticleImporter $importer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Chemin du fichier .md')] string $fichier,
        #[Option(description: 'Met à jour l\'article s\'il existe déjà (même slug), sans changer son statut')] bool $update = false,
    ): int {
        if (!is_file($fichier) || !is_readable($fichier)) {
            $io->error(sprintf('Fichier introuvable : %s', $fichier));

            return Command::FAILURE;
        }

        try {
            $resultat = $this->importer->importer(
                (string) file_get_contents($fichier),
                dossierSource: dirname((string) realpath($fichier)),
                miseAJour: $update,
            );
        } catch (ImportException $e) {
            $io->error($e->getMessage() . ($update ? '' : ' Pour mettre à jour un article existant, relancez avec --update.'));

            return Command::FAILURE;
        }

        foreach ($resultat->avertissements as $avertissement) {
            $io->warning($avertissement);
        }

        $article = $resultat->article;
        $io->success(sprintf(
            '%s : "%s" (%s, %d min de lecture, %d section%s)',
            $resultat->creation ? 'Article créé en brouillon' : 'Article mis à jour',
            $article->getTitre(),
            $article->getEtat(),
            $article->getTempsLecture(),
            count($article->getSommaire()),
            count($article->getSommaire()) > 1 ? 's' : ''
        ));
        $io->text([
            'Aperçu : ' . $this->urlGenerator->generate('blog_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            'Admin  : ' . $this->urlGenerator->generate('admin_blog_edit', ['id' => $article->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return Command::SUCCESS;
    }
}
