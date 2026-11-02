<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Document;
use App\Entity\Project;
use App\Entity\ProjectStep;
use App\Repository\TarifRepository;
use App\Service\DevisPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/devis')]
#[IsGranted('ROLE_ADMIN')]
class DevisController extends AbstractController
{
    public function __construct(
        private readonly TarifRepository $tarifRepository,
        private readonly EntityManagerInterface $em,
        private readonly DevisPdfGenerator $pdfGenerator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/projet/{id}/nouveau', name: 'admin_devis_new_project', methods: ['GET', 'POST'])]
    public function newForProject(Project $project, Request $request): Response
    {
        return $this->handle($request, $project->getClient(), $project);
    }

    #[Route('/client/{id}/nouveau', name: 'admin_devis_new_client', methods: ['GET', 'POST'])]
    public function newForClient(Client $client, Request $request): Response
    {
        return $this->handle($request, $client, null);
    }

    private function handle(Request $request, Client $client, ?Project $project): Response
    {
        $old = ['tarifs' => [], 'quantites' => [], 'libres' => [], 'remise' => '', 'remise_type' => 'eur', 'remise_libelle' => '', 'project' => '', 'titre' => 'Site web'];

        if ($request->isMethod('POST')) {
            $old = [
                'tarifs' => array_map('intval', $request->request->all('tarifs')),
                'quantites' => $request->request->all('quantites'),
                'libres' => $this->parseLignesLibres($request->request->all('libres')),
                'remise' => trim((string) $request->request->get('remise', '')),
                'remise_type' => $request->request->get('remise_type') === 'pct' ? 'pct' : 'eur',
                'remise_libelle' => mb_substr(trim((string) $request->request->get('remise_libelle', '')), 0, 100),
                'project' => (string) $request->request->get('project', ''),
                'titre' => trim((string) $request->request->get('titre', '')),
            ];

            $error = null;
            $lignes = [...$this->buildLignes($old['tarifs'], $old['quantites']), ...$old['libres']];
            $remise = $this->buildRemise($lignes, $old['remise'], $old['remise_type'], $old['remise_libelle']);
            if ($remise !== null) {
                $lignes[] = $remise;
            }

            $targetProject = $project;
            if ($targetProject === null && $old['project'] !== '') {
                $targetProject = $client->getProjects()->filter(
                    fn (Project $p) => (string) $p->getId() === $old['project']
                )->first() ?: null;
                if ($targetProject === null) {
                    $error = 'Projet introuvable.';
                }
            } elseif ($targetProject === null && $old['titre'] === '') {
                $error = 'Donne un titre au nouveau projet.';
            }

            if ($lignes === []) {
                $error = 'Sélectionne au moins un élément.';
            }

            if ($error === null) {
                $devis = $this->createDevis($client, $targetProject, $old['titre'], $lignes);
                $nbTaches = count(array_filter($lignes, fn (array $l) => !($l['remise'] ?? false)));

                $this->addFlash('success', sprintf(
                    'Devis %s généré (%d tâche%s ajoutée%s au projet).',
                    $devis->getNumero(),
                    $nbTaches,
                    $nbTaches > 1 ? 's' : '',
                    $nbTaches > 1 ? 's' : ''
                ));

                return $this->redirectToRoute('admin_project_show', ['id' => $devis->getProject()->getId()]);
            }

            $this->addFlash('error', $error);
        }

        return $this->render('admin/devis/new.html.twig', [
            'client' => $client,
            'project' => $project,
            'groups' => $this->tarifRepository->findGroupedByCategorie(true),
            'old' => $old,
        ]);
    }

    /**
     * @param int[] $ids
     * @param array<int|string, mixed> $quantites
     *
     * @return list<array{libelle: string, description: ?string, prix: float, quantite: int, unite: ?string}>
     */
    private function buildLignes(array $ids, array $quantites): array
    {
        $lignes = [];
        foreach ($this->tarifRepository->findBy(['actif' => true], ['position' => 'ASC', 'id' => 'ASC']) as $tarif) {
            if (!$tarif->isObligatoire() && !in_array($tarif->getId(), $ids, true)) {
                continue;
            }

            $quantite = $tarif->isObligatoire()
                ? 1
                : max(1, min(99, (int) ($quantites[$tarif->getId()] ?? 1)));

            $lignes[] = [
                'libelle' => $tarif->getLibelle(),
                'description' => $tarif->getDescription(),
                'prix' => $tarif->getPrix(),
                'quantite' => $quantite,
                'unite' => $tarif->getUnite(),
            ];
        }

        return $lignes;
    }

    /**
     * Ligne de remise (montant négatif) calculée sur le sous-total, sans jamais dépasser celui-ci.
     *
     * @param list<array{prix: float, quantite: int}> $lignes
     *
     * @return array{libelle: string, description: null, prix: float, quantite: int, unite: null, remise: true}|null
     */
    private function buildRemise(array $lignes, string $valeur, string $type, string $libelle): ?array
    {
        $valeur = round((float) str_replace(',', '.', $valeur), 2);
        if ($valeur <= 0) {
            return null;
        }

        $sousTotal = array_sum(array_map(fn (array $l) => $l['prix'] * $l['quantite'], $lignes));
        $pourcentage = $type === 'pct' ? min(100.0, $valeur) : null;
        $montant = round(min($sousTotal, $pourcentage !== null ? $sousTotal * $pourcentage / 100 : $valeur), 2);
        if ($montant <= 0) {
            return null;
        }

        if ($libelle === '') {
            $libelle = $pourcentage !== null
                ? sprintf('Remise %s %%', rtrim(rtrim(number_format($pourcentage, 2, ',', ''), '0'), ','))
                : 'Remise';
        }

        return ['libelle' => $libelle, 'description' => null, 'prix' => -$montant, 'quantite' => 1, 'unite' => null, 'remise' => true];
    }

    /**
     * Lignes saisies à la main : une ligne sans libellé est ignorée.
     *
     * @param array<int|string, mixed> $rows
     *
     * @return list<array{libelle: string, description: ?string, prix: float, quantite: int, unite: ?string}>
     */
    private function parseLignesLibres(array $rows): array
    {
        $lignes = [];
        foreach ($rows as $row) {
            $libelle = is_array($row) ? trim((string) ($row['libelle'] ?? '')) : '';
            if ($libelle === '') {
                continue;
            }

            $lignes[] = [
                'libelle' => mb_substr($libelle, 0, 150),
                'description' => null,
                'prix' => max(0.0, round((float) str_replace(',', '.', (string) ($row['prix'] ?? '0')), 2)),
                'quantite' => max(1, min(99, (int) ($row['quantite'] ?? 1))),
                'unite' => null,
                'libre' => true,
            ];
        }

        return $lignes;
    }

    /**
     * @param list<array{libelle: string, description: ?string, prix: float, quantite: int, unite: ?string}> $lignes
     */
    private function createDevis(Client $client, ?Project $project, string $titreProjet, array $lignes): Devis
    {
        return $this->em->wrapInTransaction(function () use ($client, $project, $titreProjet, $lignes): Devis {
            if ($project === null) {
                $project = new Project();
                $project->setTitre($titreProjet);
                $project->setClient($client);
                $this->em->persist($project);
            }

            $position = $project->getSteps()->filter(fn (ProjectStep $s) => $s->getParent() === null)->count();
            $total = 0.0;

            foreach ($lignes as $ligne) {
                $quantite = $ligne['quantite'];
                $total += $ligne['prix'] * $quantite;

                // La remise n'est pas une tâche : elle n'apparaît que dans le devis
                if ($ligne['remise'] ?? false) {
                    continue;
                }

                $step = new ProjectStep();
                $step->setTitre($quantite > 1
                    ? sprintf('%s (×%d%s)', $ligne['libelle'], $quantite, $ligne['unite'] === 'h' ? ' h' : '')
                    : $ligne['libelle']);
                $step->setDescription($ligne['description']);
                $step->setProject($project);
                $step->setPosition($position++);
                $this->em->persist($step);
            }

            $devis = new Devis();
            $devis->setProject($project);
            $devis->setTotal($total);
            $devis->setLignes($lignes);
            $this->em->persist($devis);
            $this->em->flush();

            $devis->setNumero(sprintf('DEV-%s-%04d', $devis->getCreatedAt()->format('Y'), $devis->getId()));

            $directory = $this->projectDir . '/public/uploads/documents';
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            $filename = strtolower($devis->getNumero()) . '-' . bin2hex(random_bytes(4)) . '.pdf';
            file_put_contents($directory . '/' . $filename, $this->pdfGenerator->generate($devis));

            $document = new Document();
            $document->setNom('Devis ' . $devis->getNumero());
            $document->setType('devis');
            $document->setMontant($total);
            $document->setProject($project);
            $document->setFichier($filename);
            $this->em->persist($document);

            $devis->setDocument($document);
            $this->em->flush();

            return $devis;
        });
    }
}
