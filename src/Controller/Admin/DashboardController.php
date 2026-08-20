<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Document;
use App\Entity\Intervention;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Contact::class);

        $debutMois = new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('Europe/Paris'));

        $totalClients = $em->getRepository(Client::class)->count([]);

        $projetsEnCours = $em->getRepository(Project::class)->count(['statut' => 'en_cours']);

        $interventionsCeMois = $em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Intervention::class, 'i')
            ->where('i.date >= :debut')
            ->setParameter('debut', $debutMois)
            ->getQuery()
            ->getSingleScalarResult();

        $montantInterventionsCeMois = $em->createQueryBuilder()
            ->select('COALESCE(SUM(i.montant), 0)')
            ->from(Intervention::class, 'i')
            ->where('i.date >= :debut')
            ->setParameter('debut', $debutMois)
            ->getQuery()
            ->getSingleScalarResult();
        
        $montantFacturesCeMois = $em->createQueryBuilder()
            ->select('COALESCE(SUM(d.montant), 0)')
            ->from(Document::class, 'd')
            ->where('d.type = :type')
            ->andWhere('d.statutPaiement = :statut')
            ->andWhere('d.createdAt >= :debut')
            ->setParameter('type', 'facture')
            ->setParameter('statut', 'payee')
            ->setParameter('debut', $debutMois)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'nouveauxMessages' => $repo->count(['statut' => 'nouveau']),
            'derniersMessages' => $repo->findBy([], ['createdAt' => 'DESC'], 5),
            'totalClients' => $totalClients,
            'projetsEnCours' => $projetsEnCours,
            'interventionsCeMois' => $interventionsCeMois,
            'montantInterventionsCeMois' => $montantInterventionsCeMois,
            'montantFacturesCeMois' => $montantFacturesCeMois,
            'montantTotalCeMois' => $montantInterventionsCeMois + $montantFacturesCeMois,
        ]);
    }
}