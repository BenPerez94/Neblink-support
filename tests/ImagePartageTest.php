<?php

namespace App\Tests;

use App\Entity\Article;
use App\Service\Blog\ImagePartage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImagePartageTest extends WebTestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir() . '/neblink-partage-' . bin2hex(random_bytes(4));
        mkdir($this->dossier . '/public/uploads/blog', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dossier, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $element) {
            $element->isDir() ? rmdir($element->getPathname()) : unlink($element->getPathname());
        }
        rmdir($this->dossier);
        parent::tearDown();
    }

    #[RequiresPhpExtension('gd')]
    public function testUnePhotoPortraitDonneUneImage1200x630Legere(): void
    {
        // Photo en portrait, comme celle de l'article sur la mise à jour Windows
        $source = imagecreatetruecolor(1920, 2400);
        for ($y = 0; $y < 2400; $y += 40) {
            imagefilledrectangle($source, 0, $y, 1920, $y + 39, (int) imagecolorallocate($source, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        imagejpeg($source, $this->dossier . '/public/uploads/blog/photo-portrait.jpg', 95);

        $article = (new Article())->setCouverture('photo-portrait.jpg')->setSlug('test');
        $chemin = (new ImagePartage($this->dossier, new NullLogger()))->cheminPublic($article);

        self::assertSame('/uploads/blog/partage/photo-portrait.jpg', $chemin);

        $fichier = $this->dossier . '/public' . $chemin;
        [$largeur, $hauteur, $type] = getimagesize($fichier);
        self::assertSame([1200, 630, IMAGETYPE_JPEG], [$largeur, $hauteur, $type]);
        self::assertLessThanOrEqual(200 * 1024, filesize($fichier));
    }

    public function testSansCouvertureIlNYAPasDImageDePartage(): void
    {
        $service = new ImagePartage($this->dossier, new NullLogger());

        self::assertNull($service->cheminPublic(new Article()));
    }

    public function testUnArticleSansCouvertureDeclareLImageDuSiteAuBonFormat(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $article = (new Article())
            ->setTitre('Article sans couverture')
            ->setSlug('partage-sans-couverture')
            ->setContenu('<p>Contenu</p>')
            ->setStatut(Article::STATUT_PUBLIE)
            ->setDatePublication(new \DateTimeImmutable('-1 hour'));
        $em->persist($article);
        $em->flush();

        try {
            $crawler = $client->request('GET', '/blog/partage-sans-couverture');
            self::assertResponseIsSuccessful();

            self::assertStringEndsWith('/images/og-image.jpg', $crawler->filter('meta[property="og:image"]')->attr('content'));
            self::assertSame('1200', $crawler->filter('meta[property="og:image:width"]')->attr('content'));
            self::assertSame('630', $crawler->filter('meta[property="og:image:height"]')->attr('content'));
            // Bloc de partage présent deux fois : en bas de l'article (mobile) et dans la sidebar
            self::assertCount(2, $crawler->filter('[data-native-share]'));
        } finally {
            $em->remove($em->getRepository(Article::class)->findOneBy(['slug' => 'partage-sans-couverture']));
            $em->flush();
        }
    }
}
