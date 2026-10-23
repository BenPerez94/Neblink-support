<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], format: 'xml')]
    public function index(ArticleRepository $articleRepository, CategoryRepository $categoryRepository): Response
    {
        $articles = $articleRepository->findAllPublished();
        $compteParCategorie = $articleRepository->countPublishedByCategory();

        $response = $this->render('sitemap.xml.twig', [
            'articles' => $articles,
            'categories' => array_filter(
                $categoryRepository->findBy([], ['nom' => 'ASC']),
                fn ($category) => isset($compteParCategorie[$category->getId()])
            ),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
