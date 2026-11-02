<?php

namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Entity\Echeance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class EcheanceController extends AbstractController
{
    #[Route('/devis/{id}/echeancier', name: 'admin_devis_echeancier', methods: ['GET', 'POST'])]
    public function echeancier(Devis $devis, Request $request, EntityManagerInterface $em): Response
    {
        $verrouille = $devis->getEcheances()->exists(fn (int $i, Echeance $e) => $e->isPayee());
        $rows = $this->rowsFromDevis($devis);

        if ($request->isMethod('POST') && !$verrouille) {
            $rows = $this->rowsFromRequest($request);
            $error = $this->validate($rows, $devis->getTotal());

            if ($error === null) {
                foreach ($devis->getEcheances()->toArray() as $existing) {
                    $devis->getEcheances()->removeElement($existing);
                }
                foreach ($rows as $position => $row) {
                    $devis->addEcheance(
                        (new Echeance())
                            ->setLibelle($row['libelle'])
                            ->setMontant($row['montant'])
                            ->setDateEcheance($row['date'] !== '' ? new \DateTimeImmutable($row['date'], new \DateTimeZone('Europe/Paris')) : null)
                            ->setPosition($position)
                    );
                }
                $devis->setStatut(Devis::STATUT_ACCEPTE);
                $em->flush();

                $this->addFlash('success', 'Devis accepté, échéancier enregistré.');

                return $this->redirectToRoute('admin_project_show', ['id' => $devis->getProject()->getId()]);
            }

            $this->addFlash('error', $error);
        }

        return $this->render('admin/devis/echeancier.html.twig', [
            'devis' => $devis,
            'project' => $devis->getProject(),
            'rows' => $rows,
            'verrouille' => $verrouille,
        ]);
    }

    #[Route('/echeances/{id}/paiement', name: 'admin_echeance_toggle', methods: ['POST'])]
    public function togglePaiement(Echeance $echeance, EntityManagerInterface $em): Response
    {
        $echeance->setPayeeLe($echeance->isPayee() ? null : new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $em->flush();

        return $this->redirectToRoute('admin_project_show', ['id' => $echeance->getDevis()->getProject()->getId()]);
    }

    /**
     * @return list<array{libelle: string, montant: float, date: string}>
     */
    private function rowsFromDevis(Devis $devis): array
    {
        $rows = [];
        foreach ($devis->getEcheances() as $echeance) {
            $rows[] = [
                'libelle' => (string) $echeance->getLibelle(),
                'montant' => $echeance->getMontant(),
                'date' => $echeance->getDateEcheance()?->format('Y-m-d') ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{libelle: string, montant: float, date: string}>
     */
    private function rowsFromRequest(Request $request): array
    {
        $rows = [];
        foreach ($request->request->all('echeances') as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = trim((string) ($row['date'] ?? ''));
            $rows[] = [
                'libelle' => trim((string) ($row['libelle'] ?? '')) ?: 'Échéance ' . (count($rows) + 1),
                'montant' => round((float) str_replace(',', '.', (string) ($row['montant'] ?? '0')), 2),
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{libelle: string, montant: float, date: string}> $rows
     */
    private function validate(array $rows, float $total): ?string
    {
        if ($rows === []) {
            return 'Ajoute au moins une échéance.';
        }
        foreach ($rows as $row) {
            if ($row['montant'] <= 0) {
                return 'Chaque échéance doit avoir un montant positif.';
            }
        }
        $somme = array_sum(array_column($rows, 'montant'));
        if (abs($somme - $total) > 0.01) {
            return sprintf('La somme des échéances (%s €) doit être égale au total du devis (%s €).', number_format($somme, 2, ',', ' '), number_format($total, 2, ',', ' '));
        }

        return null;
    }
}
