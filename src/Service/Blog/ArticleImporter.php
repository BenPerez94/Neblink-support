<?php

namespace App\Service\Blog;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Crée (ou met à jour) un article à partir d'un fichier Markdown avec un en-tête YAML.
 * Utilisé par la page d'import de l'admin et par la commande app:blog:import.
 *
 * ---
 * titre: Comment reconnaître un e-mail de phishing
 * slug: reconnaitre-email-phishing
 * extrait: Les signes qui doivent vous alerter avant de cliquer.
 * categorie: Sécurité
 * tags: [arnaque, email]
 * couverture: images/phishing.jpg     # chemin relatif au fichier .md (commande uniquement)
 * couverture_alt: Une boîte mail avec un message suspect
 * ---
 *
 * Un encart astuce s'écrit "> [!ASTUCE]" en première ligne d'une citation.
 */
class ArticleImporter
{
    public const CHAMPS_CONNUS = ['titre', 'slug', 'extrait', 'categorie', 'tags', 'meta_titre', 'meta_description', 'couverture', 'couverture_alt'];

    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepository $articleRepository,
        private BlogManager $blogManager,
    ) {
    }

    /**
     * @param string      $markdown       contenu complet du fichier (en-tête + texte)
     * @param File|null   $couverture     image envoyée à part (prioritaire sur le champ "couverture")
     * @param string|null $dossierSource  dossier du fichier .md, pour résoudre le champ "couverture"
     * @param bool        $miseAJour      met à jour l'article existant de même adresse au lieu d'échouer
     *
     * @throws ImportException
     */
    public function importer(string $markdown, ?File $couverture = null, ?string $dossierSource = null, bool $miseAJour = false): ResultatImport
    {
        [$meta, $corps] = $this->separerEnTete($markdown);
        $avertissements = [];

        $inconnus = array_diff(array_keys($meta), self::CHAMPS_CONNUS);
        if ($inconnus) {
            $avertissements[] = 'Champs ignorés dans l\'en-tête : ' . implode(', ', $inconnus) . '.';
        }

        $titre = trim((string) ($meta['titre'] ?? ''));
        if ($titre === '') {
            throw new ImportException('Le champ "titre" est obligatoire dans l\'en-tête du fichier.');
        }
        if (trim($corps) === '') {
            throw new ImportException('Le fichier ne contient pas de texte après l\'en-tête.');
        }

        $slugSouhaite = $this->blogManager->slugify((string) ($meta['slug'] ?? $titre));
        $article = $this->articleRepository->findOneBy(['slug' => $slugSouhaite]);

        if ($article !== null && !$miseAJour) {
            throw new ImportException(sprintf('Un article existe déjà à l\'adresse /blog/%s. Changez le champ "slug" ou supprimez l\'ancien article.', $slugSouhaite));
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

        $this->blogManager->applyContenu($article, $this->markdownVersHtml($corps));

        if ($couverture !== null) {
            $article->setCouvertureFile($couverture);
        } elseif (!empty($meta['couverture'])) {
            if ($dossierSource === null) {
                $avertissements[] = 'Le champ "couverture" de l\'en-tête est ignoré : ajoutez l\'image avec le formulaire.';
            } else {
                $article->setCouvertureFile($this->copieImage($dossierSource . DIRECTORY_SEPARATOR . $meta['couverture']));
            }
        }

        $article->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        $this->em->persist($article);
        $this->em->flush();

        return new ResultatImport($article, $creation, $avertissements);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function separerEnTete(string $contenu): array
    {
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

        if (!preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $contenu, $m)) {
            throw new ImportException('En-tête introuvable : le fichier doit commencer par une ligne "---", suivie des champs (titre, extrait…) puis d\'une autre ligne "---".');
        }

        try {
            $meta = Yaml::parse($m[1]);
        } catch (ParseException $e) {
            throw new ImportException('En-tête invalide, ligne ' . $e->getParsedLine() . ' : vérifiez les guillemets et les deux-points.');
        }

        if (!is_array($meta)) {
            throw new ImportException('En-tête invalide : il doit contenir des champs de la forme "titre: …".');
        }

        return [$meta, $m[2]];
    }

    private function markdownVersHtml(string $markdown): string
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

    /**
     * Vich déplace le fichier reçu : on lui confie une copie temporaire de l'image.
     */
    private function copieImage(string $chemin): UploadedFile
    {
        if (!is_file($chemin)) {
            throw new ImportException(sprintf('Image de couverture introuvable : %s', $chemin));
        }

        $copie = tempnam(sys_get_temp_dir(), 'blog');
        copy($chemin, $copie);

        return new UploadedFile($copie, basename($chemin), mime_content_type($chemin) ?: null, null, true);
    }
}
