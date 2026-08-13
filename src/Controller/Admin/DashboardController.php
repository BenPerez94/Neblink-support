<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
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

        $debutMois = new \DateTimeImmutable('first day of this month 00:00:00');

        $traitesCeMois = $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->where('c.statut = :statut')
            ->andWhere('c.createdAt >= :debut')
            ->setParameter('statut', 'traite')
            ->setParameter('debut', $debutMois)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'nouveauxMessages' => $repo->count(['statut' => 'nouveau']),
            'enCoursMessages' => $repo->count(['statut' => 'en_cours']),
            'traitesCeMois' => $traitesCeMois,
            'derniersMessages' => $repo->findBy([], ['createdAt' => 'DESC'], 5),
            'nouveauxMessagesCount' => $repo->count(['statut' => 'nouveau']),
        ]);
    }
}