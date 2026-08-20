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
            ],
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

    #[Route('/{id}/supprimer', name: 'admin_intervention_delete', methods: ['POST'])]
    public function delete(Intervention $intervention, EntityManagerInterface $em): Response
    {
        $clientId = $intervention->getClient()->getId();
        $em->remove($intervention);
        $em->flush();

        return $this->redirectToRoute('admin_client_show', ['id' => $clientId]);
    }
}