<?php

namespace App\Tests;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le serveur de production tourne en UTC : un article programmé à une heure de Paris
 * doit être publié à cette heure-là, pas 2 heures plus tard.
 */
class FuseauHoraireTest extends WebTestCase
{
    private string $fuseauInitial;

    protected function setUp(): void
    {
        $this->fuseauInitial = date_default_timezone_get();
        // Simule la configuration PHP d'o2switch
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        foreach ($em->getRepository(Article::class)->findAll() as $article) {
            $em->remove($article);
        }
        $em->flush();

        parent::tearDown();
        date_default_timezone_set($this->fuseauInitial);
    }

    public function testArticleProgrammeEstPublieALHeureDeParis(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Programmé 30 minutes avant l'heure actuelle de Paris : il doit déjà être en ligne
        $article = (new Article())
            ->setTitre('Article programmé')
            ->setSlug('article-programme-fuseau')
            ->setContenu('<p>Contenu</p>')
            ->setStatut(Article::STATUT_PUBLIE)
            ->setDatePublication(new \DateTimeImmutable('-30 minutes', new \DateTimeZone('Europe/Paris')));
        $em->persist($article);
        $em->flush();
        $em->clear();

        $relu = $em->getRepository(Article::class)->findOneBy(['slug' => 'article-programme-fuseau']);
        self::assertSame('publie', $relu->getEtat());

        $client->request('GET', '/blog/article-programme-fuseau');
        self::assertResponseIsSuccessful();
    }
}
