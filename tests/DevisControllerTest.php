<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Project;
use App\Entity\Tarif;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class DevisControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Client $clientEntity;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'devis-test@example.com'])
            ?? (new User())->setEmail('devis-test@example.com')->setPassword('inutilise');
        $admin->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();

        $this->supprimerClientDeTest();
        $admin = $this->em->getRepository(User::class)->find($admin->getId());
        $this->clientEntity = (new Client())->setNom('Client devis test')->setEmail('client-devis-test@example.com');
        $this->em->persist($this->clientEntity);
        $this->em->flush();

        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        $this->supprimerClientDeTest();
        parent::tearDown();
    }

    public function testDevisDepuisUnProjetExistant(): void
    {
        $project = (new Project())->setTitre('Site test')->setClient($this->clientEntity);
        $this->em->persist($project);
        $this->em->flush();

        $this->client->request('GET', '/admin/devis/projet/' . $project->getId() . '/nouveau');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Socle du site');
        self::assertSelectorTextContains('body', 'Obligatoire');

        $pageSimple = $this->tarif('Page simple');
        $modifs = $this->tarif('Modifications au-delà des 2 tours inclus');

        $this->client->request('POST', '/admin/devis/projet/' . $project->getId() . '/nouveau', [
            'tarifs' => [$pageSimple->getId(), $modifs->getId()],
            'quantites' => [$pageSimple->getId() => 3, $modifs->getId() => 2],
        ]);
        self::assertResponseRedirects('/admin/projets/' . $project->getId());

        $this->em->clear();
        $project = $this->em->getRepository(Project::class)->find($project->getId());

        // Socle (obligatoire, ajouté même sans être coché) + Page simple ×3 + Modifications ×2 h
        $titres = array_map(fn ($s) => $s->getTitre(), $project->getSteps()->toArray());
        self::assertSame(['Socle du site', 'Page simple (×3)', 'Modifications au-delà des 2 tours inclus (×2 h)'], $titres);

        $devis = $this->em->getRepository(Devis::class)->findOneBy(['project' => $project]);
        self::assertNotNull($devis);
        self::assertSame(390.0 + 3 * 90.0 + 2 * 40.0, $devis->getTotal());
        self::assertMatchesRegularExpression('/^DEV-\d{4}-\d{4}$/', $devis->getNumero());

        $document = $devis->getDocument();
        self::assertNotNull($document);
        self::assertSame('devis', $document->getType());
        self::assertSame($devis->getTotal(), $document->getMontant());

        $path = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getFichier();
        self::assertFileExists($path);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($path));
    }

    public function testDevisDepuisUnClientCreeUnNouveauProjet(): void
    {
        $this->client->request('GET', '/admin/devis/client/' . $this->clientEntity->getId() . '/nouveau');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/admin/devis/client/' . $this->clientEntity->getId() . '/nouveau', [
            'project' => '',
            'titre' => 'Nouveau site créé par devis',
            'tarifs' => [],
        ]);
        self::assertResponseRedirects();

        $this->em->clear();
        $project = $this->em->getRepository(Project::class)->findOneBy(['titre' => 'Nouveau site créé par devis']);
        self::assertNotNull($project);
        self::assertSame($this->clientEntity->getId(), $project->getClient()->getId());
        self::assertCount(1, $project->getSteps(), 'Seul le socle obligatoire est ajouté.');
    }

    public function testRefuseUnNouveauProjetSansTitre(): void
    {
        $this->client->request('POST', '/admin/devis/client/' . $this->clientEntity->getId() . '/nouveau', [
            'project' => '',
            'titre' => '',
        ]);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Project::class)->findBy(['client' => $this->clientEntity->getId()]));
    }

    public function testEcheancierEnregistreEtAccepteLeDevis(): void
    {
        $devis = $this->creerDevisSimple(); // socle seul : 390 €

        $this->client->request('GET', '/admin/devis/' . $devis->getId() . '/echeancier');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acompte 30 %');

        $this->client->request('POST', '/admin/devis/' . $devis->getId() . '/echeancier', ['echeances' => [
            ['libelle' => 'Acompte', 'montant' => '117', 'date' => '2026-11-10'],
            ['libelle' => 'Solde', 'montant' => '273,00', 'date' => ''],
        ]]);
        self::assertResponseRedirects('/admin/projets/' . $devis->getProject()->getId());

        $this->em->clear();
        $devis = $this->em->getRepository(Devis::class)->find($devis->getId());
        self::assertSame(Devis::STATUT_ACCEPTE, $devis->getStatut());
        self::assertCount(2, $devis->getEcheances());
        self::assertSame([117.0, 273.0], array_map(fn ($e) => $e->getMontant(), $devis->getEcheances()->toArray()));
        self::assertSame('2026-11-10', $devis->getEcheances()->first()->getDateEcheance()->format('Y-m-d'));
        self::assertNull($devis->getEcheances()->last()->getDateEcheance());

        // La page projet affiche le suivi des paiements
        $this->client->request('GET', '/admin/projets/' . $devis->getProject()->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acompte');
        self::assertSelectorTextContains('body', 'En attente');
    }

    public function testEcheancierRefuseUneSommeIncorrecte(): void
    {
        $devis = $this->creerDevisSimple();

        $this->client->request('POST', '/admin/devis/' . $devis->getId() . '/echeancier', ['echeances' => [
            ['libelle' => 'Acompte', 'montant' => '100', 'date' => ''],
            ['libelle' => 'Solde', 'montant' => '100', 'date' => ''],
        ]]);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $devis = $this->em->getRepository(Devis::class)->find($devis->getId());
        self::assertSame(Devis::STATUT_GENERE, $devis->getStatut());
        self::assertCount(0, $devis->getEcheances());
    }

    public function testPaiementBasculeEtVerrouilleLEcheancier(): void
    {
        $devis = $this->creerDevisSimple();
        $this->client->request('POST', '/admin/devis/' . $devis->getId() . '/echeancier', ['echeances' => [
            ['libelle' => 'Acompte', 'montant' => '195', 'date' => ''],
            ['libelle' => 'Solde', 'montant' => '195', 'date' => ''],
        ]]);

        $this->em->clear();
        $echeance = $this->em->getRepository(Devis::class)->find($devis->getId())->getEcheances()->first();

        $this->client->request('POST', '/admin/echeances/' . $echeance->getId() . '/paiement');
        self::assertResponseRedirects();

        $this->em->clear();
        $devis = $this->em->getRepository(Devis::class)->find($devis->getId());
        self::assertTrue($devis->getEcheances()->first()->isPayee());

        // Une échéance payée verrouille la modification
        $this->client->request('POST', '/admin/devis/' . $devis->getId() . '/echeancier', ['echeances' => [
            ['libelle' => 'Tout', 'montant' => '390', 'date' => ''],
        ]]);
        self::assertResponseIsSuccessful();
        $this->em->clear();
        self::assertCount(2, $this->em->getRepository(Devis::class)->find($devis->getId())->getEcheances());

        // Repasser en attente
        $this->client->request('POST', '/admin/echeances/' . $echeance->getId() . '/paiement');
        $this->em->clear();
        self::assertFalse($this->em->getRepository(Devis::class)->find($devis->getId())->getEcheances()->first()->isPayee());
    }

    public function testEspaceClientAfficheLesPaiementsEnLectureSeule(): void
    {
        $devis = $this->creerDevisSimple();
        $this->client->request('POST', '/admin/devis/' . $devis->getId() . '/echeancier', ['echeances' => [
            ['libelle' => 'Acompte', 'montant' => '117', 'date' => ''],
            ['libelle' => 'Solde', 'montant' => '273', 'date' => ''],
        ]]);

        $this->em->clear();
        $devis = $this->em->getRepository(Devis::class)->find($devis->getId());
        $devis->getEcheances()->first()->setPayeeLe(new \DateTimeImmutable('2026-11-02'));
        $this->em->flush();
        $this->em->clear();

        $client = $this->em->getRepository(Client::class)->find($this->clientEntity->getId());
        static::getContainer()->get('request_stack')->push(Request::create('/espace-client'));
        $html = static::getContainer()->get('twig')->render('espace_client/dashboard.html.twig', [
            'client' => $client,
            'projects' => $client->getProjects(),
        ]);

        self::assertStringContainsString('Paiements', $html);
        self::assertStringContainsString('117 € réglés sur 390 €', $html);
        self::assertStringContainsString('Réglée le 02/11/2026', $html);
        self::assertStringContainsString('À régler', $html);
        self::assertStringNotContainsString('/echeances/', $html);
    }

    private function creerDevisSimple(): Devis
    {
        $project = (new Project())->setTitre('Site échéancier')->setClient($this->clientEntity);
        $this->em->persist($project);
        $this->em->flush();

        $this->client->request('POST', '/admin/devis/projet/' . $project->getId() . '/nouveau', ['tarifs' => []]);
        self::assertResponseRedirects();

        $this->em->clear();
        $devis = $this->em->getRepository(Devis::class)->findOneBy(['project' => $project->getId()]);
        self::assertSame(390.0, $devis->getTotal());

        return $devis;
    }

    private function tarif(string $libelle): Tarif
    {
        $tarif = $this->em->getRepository(Tarif::class)->findOneBy(['libelle' => $libelle]);
        self::assertNotNull($tarif, "Tarif « $libelle » introuvable : la migration a-t-elle été exécutée ?");

        return $tarif;
    }

    private function supprimerClientDeTest(): void
    {
        $this->em->clear();
        $client = $this->em->getRepository(Client::class)->findOneBy(['email' => 'client-devis-test@example.com']);
        if ($client === null) {
            return;
        }

        // Passe par l'ORM pour que Vich supprime aussi les PDF générés
        foreach ($client->getProjects() as $project) {
            $this->em->remove($project);
        }
        $this->em->remove($client);
        $this->em->flush();
        $this->em->clear();
    }
}
