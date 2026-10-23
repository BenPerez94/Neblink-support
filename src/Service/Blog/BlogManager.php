<?php

namespace App\Service\Blog;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Logique commune à l'admin et à la commande d'import : slugs uniques, tags, contenu.
 */
class BlogManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private ContentProcessor $contentProcessor,
    ) {
    }

    public function slugify(string $texte): string
    {
        return (new AsciiSlugger('fr'))->slug($texte)->lower()->toString();
    }

    public function uniqueArticleSlug(string $souhaite, ?Article $article = null): string
    {
        $base = $this->slugify($souhaite) ?: 'article';
        $slug = $base;
        $i = 2;

        while (($existant = $this->articleRepository->findOneBy(['slug' => $slug])) && $existant !== $article) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function applyContenu(Article $article, string $html): void
    {
        $resultat = $this->contentProcessor->process($html);

        $article->setContenu($resultat['html']);
        $article->setSommaire($resultat['sommaire']);
        $article->setTempsLecture($resultat['tempsLecture']);
    }

    /**
     * Remplace les tags de l'article à partir d'une liste de noms ("windows, wifi, sécurité").
     * Les tags inexistants sont créés.
     */
    public function syncTags(Article $article, string $saisie): void
    {
        $noms = array_unique(array_filter(array_map('trim', explode(',', $saisie)), fn (string $n) => $n !== ''));

        foreach ($article->getTags()->toArray() as $tag) {
            $article->removeTag($tag);
        }

        foreach ($noms as $nom) {
            $article->addTag($this->findOrCreateTag($nom));
        }
    }

    public function findOrCreateTag(string $nom): Tag
    {
        $slug = $this->slugify($nom);
        $tag = $this->tagRepository->findOneBy(['slug' => $slug]);

        if ($tag === null) {
            $tag = (new Tag())->setNom(mb_strtolower($nom))->setSlug($slug);
            $this->em->persist($tag);
        }

        return $tag;
    }

    public function findOrCreateCategory(string $nom): Category
    {
        $slug = $this->slugify($nom);
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if ($category === null) {
            $category = (new Category())->setNom($nom)->setSlug($slug);
            $this->em->persist($category);
        }

        return $category;
    }
}
