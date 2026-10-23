<?php

namespace App\Tests;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BlogImportControllerTest extends WebTestCase
{
    private const ARTICLE = __DIR__ . '/fixtures/blog/article-exemple.md';
    private const IMAGE = __DIR__ . '/fixtures/blog/couverture-exemple.png';
    private const SLUG = 'article-exemple-import';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $this->supprimerArticles();

        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'import-test@example.com'])
            ?? (new User())->setEmail('import-test@example.com')->setPassword('inutilise');
        $admin->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        // Passe par l'ORM pour que Vich supprime aussi l'image envoyée
        $this->supprimerArticles();
        parent::tearDown();
    }

    public function testImportCreeUnBrouillonAvecCouverture(): void
    {
        $this->client->request('GET', '/admin/blog/importer');
        self::assertResponseIsSuccessful();

        $this->importer(self::ARTICLE, self::IMAGE);

        $article = $this->em->getRepository(Article::class)->findOneBy(['slug' => self::SLUG]);
        self::assertNotNull($article);
        self::assertResponseRedirects('/admin/blog/' . $article->getId() . '/modifier');

        self::assertSame('Article d\'exemple : bien sauvegarder ses données ?', $article->getTitre());
        self::assertSame(Article::STATUT_BROUILLON, $article->getStatut());
        self::assertSame('Dépannage', $article->getCategory()?->getNom());
        self::assertCount(2, $article->getTags());
        self::assertNotNull($article->getCouverture());
        self::assertSame('Logo Neblink', $article->getCouvertureAlt());

        $contenu = $article->getContenu();
        self::assertStringContainsString('<aside>', $contenu);
        self::assertStringContainsString('target="_blank"', $contenu, 'Les liens externes s\'ouvrent dans un nouvel onglet');
        self::assertStringContainsString('<a href="/contact">', $contenu, 'Les liens internes restent dans le même onglet');
        self::assertSame(['premiere-partie', 'deuxieme-partie', 'un-sous-titre'], array_column($article->getSommaire(), 'id'));

        // Le brouillon n'est pas visible des visiteurs
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/blog/' . self::SLUG);
        self::assertResponseStatusCodeSame(404);
    }

    public function testImportRefuseUneAdresseDejaPrise(): void
    {
        $this->importer(self::ARTICLE);
        $this->importer(self::ARTICLE);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em->getRepository(Article::class)->count([]));
    }

    public function testImportRefuseUnFichierSansEnTete(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($fichier, "Un texte sans en-tête.\n");

        $this->importer($fichier, nomFichier: 'sans-entete.md');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Article::class)->count([]));
    }

    private function importer(string $markdown, ?string $image = null, ?string $nomFichier = null): void
    {
        // Copies temporaires : l'upload déplace les fichiers reçus
        $fichiers = ['fichier' => $this->copie($markdown, $nomFichier ?? basename($markdown), 'text/markdown')];
        if ($image !== null) {
            $fichiers['couverture'] = $this->copie($image, basename($image), 'image/png');
        }

        $this->client->request('POST', '/admin/blog/importer', files: $fichiers);
    }

    private function copie(string $source, string $nom, string $mime): UploadedFile
    {
        $copie = tempnam(sys_get_temp_dir(), 'upl');
        copy($source, $copie);

        return new UploadedFile($copie, $nom, $mime, null, true);
    }

    private function supprimerArticles(): void
    {
        foreach ($this->em->getRepository(Article::class)->findAll() as $article) {
            $this->em->remove($article);
        }
        $this->em->flush();
    }
}
