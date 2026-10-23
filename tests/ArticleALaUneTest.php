<?php

namespace App\Tests;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleALaUneTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->supprimerArticles();
    }

    protected function tearDown(): void
    {
        $this->supprimerArticles();
        parent::tearDown();
    }

    public function testSansChoixLeDernierPublieEstALaUne(): void
    {
        $this->creer('Ancien', '-3 days');
        $this->creer('Récent', '-1 day');

        self::assertSame('Récent', $this->titreALaUne());
    }

    public function testLArticleChoisiEstALaUne(): void
    {
        $this->creer('Ancien choisi', '-3 days', aLaUne: true);
        $this->creer('Récent', '-1 day');

        self::assertSame('Ancien choisi', $this->titreALaUne());
    }

    public function testUnBrouillonChoisiNeRemplacePasLaUne(): void
    {
        $this->creer('Brouillon choisi', null, aLaUne: true, statut: Article::STATUT_BROUILLON);
        $this->creer('Récent', '-1 day');

        self::assertSame('Récent', $this->titreALaUne());
    }

    public function testLEtoileNeGardeQuUnSeulArticleALaUne(): void
    {
        $premier = $this->creer('Premier', '-3 days', aLaUne: true);
        $second = $this->creer('Second', '-2 days');

        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'une-test@example.com'])
            ?? (new User())->setEmail('une-test@example.com')->setPassword('inutilise');
        $admin->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/admin/blog/' . $second->getId() . '/une');
        self::assertResponseRedirects('/admin/blog');

        $this->em->clear();
        $repository = $this->em->getRepository(Article::class);
        self::assertTrue($repository->find($second->getId())->isALaUne());
        self::assertFalse($repository->find($premier->getId())->isALaUne());
        self::assertSame('Second', $this->titreALaUne());
    }

    private function creer(string $titre, ?string $date, bool $aLaUne = false, string $statut = Article::STATUT_PUBLIE): Article
    {
        $article = (new Article())
            ->setTitre($titre)
            ->setSlug('une-' . md5($titre))
            ->setContenu('<p>Contenu</p>')
            ->setStatut($statut)
            ->setALaUne($aLaUne)
            ->setDatePublication($date !== null ? new \DateTimeImmutable($date, new \DateTimeZone('Europe/Paris')) : null);

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    private function titreALaUne(): string
    {
        $crawler = $this->client->request('GET', '/blog');
        self::assertResponseIsSuccessful();

        // L'article à la une est le seul titre h2 du cadre des résultats (les cartes sont en h3)
        return trim($crawler->filter('#blog-resultats h2')->text());
    }

    private function supprimerArticles(): void
    {
        foreach ($this->em->getRepository(Article::class)->findAll() as $article) {
            $this->em->remove($article);
        }
        $this->em->flush();
    }
}
