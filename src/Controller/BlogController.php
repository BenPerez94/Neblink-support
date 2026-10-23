<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    private const PAR_PAGE = 9;

    public function __construct(
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', name: 'blog_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $recherche = $this->recherche($request);

        // L'article à la une n'apparaît qu'en tête de la première page, hors recherche
        $aLaUne = $recherche === '' ? $this->articleRepository->findALaUne() : null;

        $articles = $this->articleRepository->findPublishedPaginated(
            $page,
            self::PAR_PAGE,
            search: $recherche,
            excludeId: $aLaUne?->getId(),
        );

        return $this->renderListe($articles, $page, $recherche, [
            'aLaUne' => $page === 1 ? $aLaUne : null,
            'route' => 'blog_index',
            'baseParams' => [],
        ]);
    }

    #[Route('/rss.xml', name: 'blog_rss', methods: ['GET'], format: 'xml')]
    public function rss(): Response
    {
        $response = $this->render('blog/rss.xml.twig', [
            'articles' => $this->articleRepository->findAllPublished(20),
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    #[Route('/categorie/{slug}', name: 'blog_category', methods: ['GET'])]
    public function category(#[MapEntity(mapping: ['slug' => 'slug'])] Category $category, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $recherche = $this->recherche($request);

        return $this->renderListe(
            $this->articleRepository->findPublishedPaginated($page, self::PAR_PAGE, category: $category, search: $recherche),
            $page,
            $recherche,
            [
                'category' => $category,
                'route' => 'blog_category',
                'baseParams' => ['slug' => $category->getSlug()],
            ]
        );
    }

    #[Route('/tag/{slug}', name: 'blog_tag', methods: ['GET'])]
    public function tag(#[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $recherche = $this->recherche($request);

        return $this->renderListe(
            $this->articleRepository->findPublishedPaginated($page, self::PAR_PAGE, tag: $tag, search: $recherche),
            $page,
            $recherche,
            [
                'tag' => $tag,
                'route' => 'blog_tag',
                'baseParams' => ['slug' => $tag->getSlug()],
            ]
        );
    }

    #[Route('/{slug}', name: 'blog_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Article $article): Response
    {
        $estPublic = $article->isPublie();

        // Brouillons et articles programmés : visibles uniquement par l'admin, en aperçu
        if (!$estPublic && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('blog/show.html.twig', [
            'article' => $article,
            'estPublic' => $estPublic,
            'articlesLies' => $this->articleRepository->findRelated($article),
        ]);

        if (!$estPublic) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Recherche saisie, limitée en longueur. Une seule lettre ne déclenche pas de recherche.
     */
    private function recherche(Request $request): string
    {
        $recherche = mb_substr(trim((string) $request->query->get('q', '')), 0, 100);

        return ArticleRepository::motsRecherche($recherche) === [] ? '' : $recherche;
    }

    private function renderListe(iterable $articles, int $page, string $recherche, array $contexte): Response
    {
        $total = count($articles);
        $totalPages = max(1, (int) ceil($total / self::PAR_PAGE));

        if ($page > $totalPages) {
            throw $this->createNotFoundException();
        }

        $baseParams = $contexte['baseParams'];

        return $this->render('blog/index.html.twig', $contexte + [
            'articles' => $articles,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'recherche' => $recherche,
            // Paramètres des liens de pagination : la recherche en cours est conservée
            'routeParams' => $recherche !== '' ? $baseParams + ['q' => $recherche] : $baseParams,
            'categories' => $this->categoryRepository->findBy([], ['nom' => 'ASC']),
            'compteParCategorie' => $this->articleRepository->countPublishedByCategory(),
            'aLaUne' => null,
            'category' => null,
            'tag' => null,
        ]);
    }
}
