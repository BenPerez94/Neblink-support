<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Intervention;
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