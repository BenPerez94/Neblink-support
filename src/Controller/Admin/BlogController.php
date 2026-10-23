<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Service\Blog\ArticleImporter;
use App\Service\Blog\BlogManager;
use App\Service\Blog\ImportException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Handler\UploadHandler;

#[Route('/admin/blog')]
#[IsGranted('ROLE_ADMIN')]
class BlogController extends AbstractController
{
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const IMAGE_MAX_SIZE = 5 * 1024 * 1024;
    private const MARKDOWN_MAX_SIZE = 1024 * 1024;

    public function __construct(private BlogManager $blogManager)
    {
    }

    #[Route('', name: 'admin_blog', methods: ['GET'])]
    public function index(Request $request, ArticleRepository $articleRepository, CategoryRepository $categoryRepository): Response
    {
        $etat = $request->query->get('etat', '');
        if (!in_array($etat, ['', 'brouillon', 'programme', 'publie'], true)) {
            $etat = '';
        }
        $categoryId = $request->query->get('categoryId', '');

        return $this->render('admin/blog/index.html.twig', [
            'articles' => $articleRepository->findForAdmin([
                'etat' => $etat !== '' ? $etat : null,
                'categoryId' => $categoryId !== '' ? (int) $categoryId : null,
            ]),
            'categories' => $categoryRepository->findBy([], ['nom' => 'ASC']),
            'selectedCategory' => $categoryId !== '' ? $categoryRepository->find((int) $categoryId) : null,
            'filterValues' => [
                'etat' => $etat,
                'categoryId' => $categoryId,
            ],
        ]);
    }

    #[Route('/nouveau', name: 'admin_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CategoryRepository $categoryRepository, TagRepository $tagRepository, UploadHandler $uploadHandler): Response
    {
        $article = new Article();

        if ($request->isMethod('POST')) {
            $errors = $this->handleForm($article, $request, $categoryRepository, $uploadHandler);

            if (empty($errors)) {
                $em->persist($article);
                $em->flush();

                $this->addFlash('success', 'Article créé.');

                if ($request->request->get('action') === 'apercu') {
                    return $this->redirectToRoute('blog_show', ['slug' => $article->getSlug()]);
                }

                return $this->redirectToRoute('admin_blog_edit', ['id' => $article->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->renderForm($article, $categoryRepository, $tagRepository, $request);
    }

    /**
     * Crée un article en brouillon à partir d'un fichier Markdown (même format que la commande app:blog:import).
     */
    #[Route('/importer', name: 'admin_blog_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ArticleImporter $importer): Response
    {
        if ($request->isMethod('POST')) {
            $fichier = $request->files->get('fichier');
            $couverture = $request->files->get('couverture');

            $erreur = $this->validateMarkdown($fichier) ?? ($couverture !== null ? $this->validateImage($couverture) : null);

            if ($erreur === null) {
                try {
                    $resultat = $importer->importer((string) file_get_contents($fichier->getPathname()), $couverture);

                    $this->addFlash('success', trim('Article importé en brouillon. ' . implode(' ', $resultat->avertissements)));

                    return $this->redirectToRoute('admin_blog_edit', ['id' => $resultat->article->getId()]);
                } catch (ImportException $e) {
                    $erreur = $e->getMessage();
                }
            }

            $this->addFlash('error', $erreur);

            return $this->render('admin/blog/import.html.twig', [], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/blog/import.html.twig');
    }

    #[Route('/{id}/modifier', name: 'admin_blog_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Article $article, Request $request, EntityManagerInterface $em, CategoryRepository $categoryRepository, TagRepository $tagRepository, UploadHandler $uploadHandler): Response
    {
        if ($request->isMethod('POST')) {
            $errors = $this->handleForm($article, $request, $categoryRepository, $uploadHandler);

            if (empty($errors)) {
                $em->flush();

                $this->addFlash('success', 'Article enregistré.');

                if ($request->request->get('action') === 'apercu') {
                    return $this->redirectToRoute('blog_show', ['slug' => $article->getSlug()]);
                }

                return $this->redirectToRoute('admin_blog_edit', ['id' => $article->getId()]);
            }

            // Rien n'est enregistré : le formulaire est réaffiché avec la saisie pour corriger
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->renderForm($article, $categoryRepository, $tagRepository, $request);
    }

    #[Route('/{id}/supprimer', name: 'admin_blog_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Article $article, EntityManagerInterface $em): Response
    {
        $em->remove($article);
        $em->flush();

        $this->addFlash('success', 'Article supprimé.');

        return $this->redirectToRoute('admin_blog');
    }

    /**
     * Upload d'une image insérée dans le contenu depuis l'éditeur. Répond en JSON avec l'URL publique.
     */
    #[Route('/image', name: 'admin_blog_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, #[Autowire('%kernel.project_dir%')] string $projectDir): JsonResponse
    {
        $file = $request->files->get('image');

        $error = $this->validateImage($file);
        if ($file === null || $error !== null) {
            return $this->json(['error' => $error ?? 'Aucune image reçue.'], Response::HTTP_BAD_REQUEST);
        }

        $nom = $this->blogManager->slugify(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $fichier = sprintf('%s-%s.%s', substr($nom, 0, 60), bin2hex(random_bytes(6)), $file->guessExtension() ?? 'jpg');

        $file->move($projectDir . '/public/uploads/blog/contenu', $fichier);

        return $this->json(['url' => '/uploads/blog/contenu/' . $fichier]);
    }

    #[Route('/categories', name: 'admin_blog_taxonomies', methods: ['GET'])]
    public function taxonomies(CategoryRepository $categoryRepository, TagRepository $tagRepository): Response
    {
        return $this->render('admin/blog/taxonomies.html.twig', [
            'categories' => $categoryRepository->findBy([], ['nom' => 'ASC']),
            'tags' => $tagRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/categories/ajouter', name: 'admin_blog_category_add', methods: ['POST'])]
    public function addCategory(Request $request, EntityManagerInterface $em, CategoryRepository $categoryRepository): Response
    {
        $nom = trim((string) $request->request->get('nom'));

        if ($nom !== '') {
            $slug = $this->blogManager->slugify($nom);

            if ($categoryRepository->findOneBy(['slug' => $slug])) {
                $this->addFlash('error', 'Cette catégorie existe déjà.');
            } else {
                $category = (new Category())
                    ->setNom($nom)
                    ->setSlug($slug)
                    ->setDescription(trim((string) $request->request->get('description')) ?: null);
                $em->persist($category);
                $em->flush();

                $this->addFlash('success', 'Catégorie ajoutée.');
            }
        }

        return $this->redirectToRoute('admin_blog_taxonomies');
    }

    #[Route('/categories/{id}/modifier', name: 'admin_blog_category_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editCategory(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        $nom = trim((string) $request->request->get('nom'));

        if ($nom !== '') {
            // Le slug ne change pas : les URLs de catégorie restent stables pour le référencement
            $category->setNom($nom);
            $category->setDescription(trim((string) $request->request->get('description')) ?: null);
            $em->flush();

            $this->addFlash('success', 'Catégorie modifiée.');
        }

        return $this->redirectToRoute('admin_blog_taxonomies');
    }

    #[Route('/categories/{id}/supprimer', name: 'admin_blog_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCategory(Category $category, EntityManagerInterface $em): Response
    {
        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('admin_blog_taxonomies');
    }

    #[Route('/tags/{id}/supprimer', name: 'admin_blog_tag_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTag(Tag $tag, EntityManagerInterface $em): Response
    {
        $em->remove($tag);
        $em->flush();

        $this->addFlash('success', 'Tag supprimé.');

        return $this->redirectToRoute('admin_blog_taxonomies');
    }

    /**
     * Applique le formulaire à l'article et retourne la liste des erreurs (vide si tout est valide).
     *
     * @return string[]
     */
    private function handleForm(Article $article, Request $request, CategoryRepository $categoryRepository, UploadHandler $uploadHandler): array
    {
        $errors = [];
        $data = $request->request;

        $titre = trim((string) $data->get('titre'));
        if ($titre === '') {
            $errors[] = 'Le titre est obligatoire.';
        }
        $article->setTitre($titre);

        $slugSaisi = trim((string) $data->get('slug'));
        $article->setSlug($this->blogManager->uniqueArticleSlug($slugSaisi !== '' ? $slugSaisi : $titre, $article));

        $article->setExtrait(trim((string) $data->get('extrait')) ?: null);
        $this->blogManager->applyContenu($article, (string) $data->get('contenu'));

        $statut = $data->get('statut');
        $article->setStatut($statut === Article::STATUT_PUBLIE ? Article::STATUT_PUBLIE : Article::STATUT_BROUILLON);

        $tz = new \DateTimeZone('Europe/Paris');
        $date = trim((string) $data->get('datePublication'));
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $date, $tz);
            if ($parsed === false) {
                $errors[] = 'La date de publication est invalide.';
            } else {
                $article->setDatePublication($parsed);
            }
        } elseif ($article->getStatut() === Article::STATUT_PUBLIE) {
            $article->setDatePublication(new \DateTimeImmutable('now', $tz));
        } else {
            $article->setDatePublication(null);
        }

        $categoryId = $data->get('categoryId');
        $article->setCategory($categoryId ? $categoryRepository->find((int) $categoryId) : null);

        $this->blogManager->syncTags($article, (string) $data->get('tags'));

        $article->setMetaTitre(trim((string) $data->get('metaTitre')) ?: null);
        $article->setMetaDescription(trim((string) $data->get('metaDescription')) ?: null);
        $article->setCouvertureAlt(trim((string) $data->get('couvertureAlt')) ?: null);

        if ($data->get('supprimerCouverture') && $article->getCouverture()) {
            $uploadHandler->remove($article, 'couvertureFile');
            $article->setCouverture(null);
        }

        $file = $request->files->get('couverture');
        if ($file !== null) {
            $error = $this->validateImage($file);
            if ($error !== null) {
                $errors[] = 'Couverture : ' . $error;
            } else {
                $article->setCouvertureFile($file);
            }
        }

        if ($article->getStatut() === Article::STATUT_PUBLIE && trim(strip_tags($article->getContenu())) === '') {
            $errors[] = 'Impossible de publier un article sans contenu.';
        }

        $article->setUpdatedAt(new \DateTimeImmutable('now', $tz));

        return $errors;
    }

    private function validateMarkdown(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return 'Choisissez le fichier .md de l\'article.';
        }
        if (!$file->isValid()) {
            return 'L\'envoi du fichier a échoué.';
        }
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['md', 'markdown', 'txt'], true)) {
            return 'Le fichier doit être un fichier Markdown (.md).';
        }
        if ($file->getSize() > self::MARKDOWN_MAX_SIZE) {
            return 'Le fichier ne doit pas dépasser 1 Mo.';
        }
        if (!mb_check_encoding((string) file_get_contents($file->getPathname()), 'UTF-8')) {
            return 'Le fichier doit être enregistré en UTF-8.';
        }

        return null;
    }

    private function validateImage(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!$file->isValid()) {
            return 'L\'envoi du fichier a échoué.';
        }
        if (!in_array($file->getMimeType(), self::IMAGE_MIME_TYPES, true)) {
            return 'Format accepté : JPG, PNG, WebP ou GIF.';
        }
        if ($file->getSize() > self::IMAGE_MAX_SIZE) {
            return 'L\'image ne doit pas dépasser 5 Mo.';
        }

        return null;
    }

    private function renderForm(Article $article, CategoryRepository $categoryRepository, TagRepository $tagRepository, Request $request): Response
    {
        return $this->render('admin/blog/form.html.twig', [
            'article' => $article,
            'categories' => $categoryRepository->findBy([], ['nom' => 'ASC']),
            'allTags' => $tagRepository->findBy([], ['nom' => 'ASC']),
        ], new Response(status: $request->isMethod('POST') ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
