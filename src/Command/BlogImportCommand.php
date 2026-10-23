<?php

namespace App\Command;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\Blog\BlogManager;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Importe un article écrit en Markdown (avec un en-tête YAML) en brouillon.
 *
 * ---
 * titre: Comment reconnaître un e-mail de phishing
 * extrait: Les signes qui doivent vous alerter avant de cliquer.
 * categorie: Sécurité
 * tags: [arnaque, email]
 * couverture: images/phishing.jpg     # chemin relatif au fichier .md
 * couverture_alt: Une boîte mail avec un message suspect
 * ---
 *
 * Un encart astuce s'écrit "> [!ASTUCE]" en première ligne d'une citation.
 */
#[AsCommand(name: 'app:blog:import', description: 'Importe un article Markdown dans le blog (en brouillon)')]
class BlogImportCommand
{
    private const CHAMPS_CONNUS = ['titre', 'slug', 'extrait', 'categorie', 'tags', 'meta_titre', 'meta_description', 'couverture', 'couverture_alt'];

    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepository $articleRepository,
        private BlogManager $blogManager,
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

        [$meta, $markdown] = $this->parseFichier((string) file_get_contents($fichier));

        $inconnus = array_diff(array_keys($meta), self::CHAMPS_CONNUS);
        if ($inconnus) {
            $io->warning('Champs ignorés dans l\'en-tête : ' . implode(', ', $inconnus));
        }

        $titre = trim((string) ($meta['titre'] ?? ''));
        if ($titre === '') {
            $io->error('Le champ "titre" est obligatoire dans l\'en-tête YAML.');

            return Command::FAILURE;
        }

        $slugSouhaite = $this->blogManager->slugify((string) ($meta['slug'] ?? $titre));
        $article = $this->articleRepository->findOneBy(['slug' => $slugSouhaite]);

        if ($article !== null && !$update) {
            $io->error(sprintf('Un article existe déjà avec l\'adresse "%s". Relancez avec --update pour le mettre à jour.', $slugSouhaite));

            return Command::FAILURE;
        }

        $creation = $article === null;
        $article ??= new Article();

        $article->setTitre($titre);
        $article->setSlug($creation ? $this->blogManager->uniqueArticleSlug($slugSouhaite) : $article->getSlug());
        $article->setExtrait(isset($meta['extrait']) ? trim((string) $meta['extrait']) : $article->getExtrait());
        $article->setMetaTitre($meta['meta_titre'] ?? $article->getMetaTitre());
        $article->setMetaDescription($meta['meta_description'] ?? $article->getMetaDescription());
        $article->setCouvertureAlt($meta['couverture_alt'] ?? $article->getCouvertureAlt());

        if (!empty($meta['categorie'])) {
            $article->setCategory($this->blogManager->findOrCreateCategory(trim((string) $meta['categorie'])));
        }

        if (isset($meta['tags'])) {
            $tags = is_array($meta['tags']) ? implode(',', $meta['tags']) : (string) $meta['tags'];
            $this->blogManager->syncTags($article, $tags);
        }

        $this->blogManager->applyContenu($article, $this->markdownToHtml($markdown));

        if (!empty($meta['couverture'])) {
            $chemin = dirname((string) realpath($fichier)) . DIRECTORY_SEPARATOR . $meta['couverture'];
            if (!is_file($chemin)) {
                $io->error(sprintf('Image de couverture introuvable : %s', $chemin));

                return Command::FAILURE;
            }

            // Vich déplace le fichier reçu : on lui confie une copie temporaire
            $copie = tempnam(sys_get_temp_dir(), 'blog');
            copy($chemin, $copie);
            $article->setCouvertureFile(new UploadedFile($copie, basename($chemin), mime_content_type($chemin) ?: null, null, true));
        }

        $article->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        $this->em->persist($article);
        $this->em->flush();

        $io->success(sprintf(
            '%s : "%s" (%s, %d min de lecture, %d section%s)',
            $creation ? 'Article créé en brouillon' : 'Article mis à jour',
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

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function parseFichier(string $contenu): array
    {
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

        if (preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $contenu, $m)) {
            return [Yaml::parse($m[1]) ?? [], $m[2]];
        }

        return [[], $contenu];
    }

    private function markdownToHtml(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $html = (string) $converter->convert($markdown);

        // "> [!ASTUCE]" (ou [!TIP], [!NOTE]) devient un encart <aside>
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NOERROR, 'UTF-8');
        foreach ($document->body->querySelectorAll('blockquote') as $citation) {
            $premier = $citation->querySelector('p');
            if ($premier === null || !preg_match('/^\s*\[!(ASTUCE|TIP|NOTE|INFO)\]\s*/iu', $premier->innerHTML, $m)) {
                continue;
            }

            $premier->innerHTML = substr($premier->innerHTML, strlen($m[0]));
            if (trim($premier->textContent) === '') {
                $premier->remove();
            }

            $encart = $document->createElement('aside');
            while ($citation->firstChild) {
                $encart->appendChild($citation->firstChild);
            }
            $citation->replaceWith($encart);
        }

        return $document->body->innerHTML;
    }
}
