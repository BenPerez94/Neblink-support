<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use App\Service\Blog\ImagePartage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Génère les images d'aperçu de partage (1200 × 630) de tous les articles qui ont une couverture.
 * Elles sont aussi créées automatiquement à la première consultation d'un article.
 */
#[AsCommand(name: 'app:blog:images-partage', description: 'Génère les images de partage des articles (1200 × 630)')]
class BlogImagesPartageCommand
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ImagePartage $imagePartage,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Régénère aussi les images déjà existantes')] bool $force = false,
    ): int {
        $generees = 0;
        $echecs = [];

        foreach ($this->articleRepository->findAll() as $article) {
            if (!$article->getCouverture()) {
                continue;
            }

            if ($this->imagePartage->cheminPublic($article, $force) !== null) {
                ++$generees;
            } else {
                $echecs[] = $article->getSlug();
            }
        }

        if ($echecs) {
            $io->error('Échec pour : ' . implode(', ', $echecs) . '. Vérifiez que l\'extension PHP GD est activée.');

            return Command::FAILURE;
        }

        $io->success(sprintf('%d image%s de partage prête%s.', $generees, $generees > 1 ? 's' : '', $generees > 1 ? 's' : ''));

        return Command::SUCCESS;
    }
}
