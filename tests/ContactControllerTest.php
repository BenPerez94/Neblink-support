<?php

namespace App\Tests;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($this->em->getRepository(Contact::class)->findAll() as $contact) {
            $this->em->remove($contact);
        }
        $this->em->flush();

        static::getContainer()->get('cache.rate_limiter_test')->clear();
    }

    public function testLeFormulaireAfficheLeCaptcha(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-controller="turnstile"][data-turnstile-sitekey-value]'));
    }

    public function testUnMessageAvecCaptchaValideEstEnvoye(): void
    {
        $this->envoyer('jeton-valide');

        self::assertResponseRedirects('/contact');
        self::assertEmailCount(1);
        self::assertSame(1, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testUnMessageSansCaptchaEstRefuse(): void
    {
        $this->envoyer(null);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testUnCaptchaInvalideEstRefuse(): void
    {
        $this->envoyer('jeton-bidon');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testPlusDeTroisEnvoisParHeureSontBloques(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->envoyer('jeton-valide');
            self::assertResponseRedirects('/contact');
        }

        $this->envoyer('jeton-valide');

        self::assertResponseStatusCodeSame(429);
        self::assertSame(3, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testLeChampPiegeBloqueLesRobots(): void
    {
        $this->envoyer('jeton-valide', ['website' => 'https://spam.example']);

        self::assertResponseRedirects('/contact');
        self::assertEmailCount(0);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count([]));
    }

    private function envoyer(?string $jeton, array $champs = []): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $formulaire = $crawler->selectButton('Envoyer ma demande')->form();

        $valeurs = $formulaire->getPhpValues();
        $valeurs['contact'] = array_merge($valeurs['contact'], [
            'nom' => 'Jean Test',
            'email' => 'jean@example.com',
            'typeBesoin' => 'depannage',
            'message' => 'Mon ordinateur ne démarre plus.',
        ], $champs);
        if ($jeton !== null) {
            $valeurs['cf-turnstile-response'] = $jeton;
        }

        $this->client->request('POST', '/contact', $valeurs);
    }
}
