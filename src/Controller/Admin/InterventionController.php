<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Intervention;
use App\Repository\ClientRepository;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/interventions')]
#[IsGranted('ROLE_ADMIN')]
class InterventionController extends AbstractController
{
    #[Route('', name: 'admin_interventions', methods: ['GET'])]
    public function index(Request $request, InterventionRepository $interventionRepository, ClientRepository $clientRepository): Response
    {
        $sort = $request->query->get('sort', 'date');
        $direction = strtolower($request->query->get('direction', 'desc'));

        if (!in_array($sort, ['date', 'client'], true)) {
            $sort = 'date';
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');
        $clientId = $request->query->get('clientId', '');
        $statut = $request->query->get('statut', '');

        if (!in_array($statut, ['', 'en_attente', 'en_cours', 'termine'], true)) {
            $statut = '';
        }

        $tz = new \DateTimeZone('Europe/Paris');

        try {
            $dateFromParsed = $dateFrom !== '' ? new \DateTimeImmutable($dateFrom, $tz) : null;
        } catch (\Exception) {
            $dateFrom = '';
            $dateFromParsed = null;
        }

        try {
            $dateToParsed = $dateTo !== '' ? new \DateTimeImmutable($dateTo . ' 23:59:59', $tz) : null;
        } catch (\Exception) {
            $dateTo = '';
            $dateToParsed = null;
        }

        $filters = [
            'dateFrom' => $dateFromParsed,
            'dateTo' => $dateToParsed,
            'clientId' => $clientId !== '' ? (int) $clientId : null,
            'statut' => $statut !== '' ? $statut : null,
        ];

        return $this->render('admin/interventions/index.html.twig', [
            'interventions' => $interventionRepository->findFiltered($filters, $sort, $direction),
            'sort' => $sort,
            'direction' => $direction,
            'clients' => $clientRepository->findBy([], ['nom' => 'ASC']),
            'selectedClient' => $filters['clientId'] ? $clientRepository->find($filters['clientId']) : null,
            'filterValues' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'clientId' => $clientId,
                'statut' => $statut,
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_intervention_show', methods: ['GET'])]
    public function show(Intervention $intervention): Response
    {
        return $this->render('admin/interventions/show.html.twig', [
            'intervention' => $intervention,
        ]);
    }

    #[Route('/ajouter/{clientId}', name: 'admin_intervention_add', methods: ['POST'])]
    public function add(int $clientId, Request $request, EntityManagerInterface $em): Response
    {
        $client = $em->getRepository(Client::class)->find($clientId);
        if (!$client) {
            throw $this->createNotFoundException();
        }

        $description = $request->request->get('description');

        if ($description) {
            $intervention = new Intervention();
            $intervention->setDescription($description);
            $intervention->setClient($client);

            $titre = trim((string) $request->request->get('titre'));
            $intervention->setTitre($titre !== '' ? $titre : null);

            $statut = $request->request->get('statut');
            if (in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
                $intervention->setStatut($statut);
            }

            $montant = $request->request->get('montant');
            if ($montant !== null && $montant !== '') {
                $intervention->setMontant((float) $montant);
            }

            $file = $request->files->get('facture');
            if ($file) {
                $intervention->setFactureFile($file);
            }

            $em->persist($intervention);
            $em->flush();

            $this->addFlash('success', 'Intervention ajoutée.');
        }

        return $this->redirectToRoute('admin_client_show', ['id' => $clientId]);
    }

    #[Route('/{id}/modifier', name: 'admin_intervention_edit', methods: ['GET', 'POST'])]
    public function edit(Intervention $intervention, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $description = trim((string) $request->request->get('description'));
            if ($description === '') {
                $this->addFlash('error', 'La description est obligatoire.');

                return $this->render('admin/interventions/edit.html.twig', [
                    'intervention' => $intervention,
                ]);
            }

            $intervention->setDescription($description);

            $titre = trim((string) $request->request->get('titre'));
            $intervention->setTitre($titre !== '' ? $titre : null);

            $statut = $request->request->get('statut');
            if (in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
                $intervention->setStatut($statut);
            }

            $montant = $request->request->get('montant');
            $intervention->setMontant(($montant !== null && $montant !== '') ? (float) $montant : null);

            $date = $request->request->get('date');
            if ($date) {
                try {
                    $intervention->setDate(new \DateTimeImmutable($date, new \DateTimeZone('Europe/Paris')));
                } catch (\Exception) {
                }
            }

            $file = $request->files->get('facture');
            if ($file) {
                $intervention->setFactureFile($file);
            }

            $em->flush();

            $this->addFlash('success', 'Intervention modifiée.');

            return $this->redirectToRoute('admin_intervention_show', ['id' => $intervention->getId()]);
        }

        return $this->render('admin/interventions/edit.html.twig', [
            'intervention' => $intervention,
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_intervention_update_statut', methods: ['POST'])]
    public function updateStatut(Intervention $intervention, Request $request, EntityManagerInterface $em): Response
    {
        $statut = $request->request->get('statut');

        if (in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
            $intervention->setStatut($statut);
            $em->flush();
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_ends_with(rtrim((string) parse_url($referer, PHP_URL_PATH), '/'), '/admin/interventions/' . $intervention->getId())) {
            return $this->redirectToRoute('admin_intervention_show', ['id' => $intervention->getId()]);
        }

        return $this->redirectToRoute('admin_client_show', ['id' => $intervention->getClient()->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_intervention_delete', methods: ['POST'])]
    public function delete(Intervention $intervention, Request $request, EntityManagerInterface $em): Response
    {
        $clientId = $intervention->getClient()->getId();
        $em->remove($intervention);
        $em->flush();

        $this->addFlash('success', 'Intervention supprimée.');

        $referer = $request->headers->get('referer');
        if ($referer && str_contains((string) parse_url($referer, PHP_URL_PATH), '/admin/interventions')) {
            return $this->redirectToRoute('admin_interventions');
        }

        return $this->redirectToRoute('admin_client_show', ['id' => $clientId]);
    }
}