<?php

namespace App\Controller\Admin;

use App\Entity\Tarif;
use App\Repository\TarifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tarifs')]
#[IsGranted('ROLE_ADMIN')]
class TarifController extends AbstractController
{
    #[Route('', name: 'admin_tarifs', methods: ['GET'])]
    public function index(TarifRepository $tarifRepository): Response
    {
        return $this->render('admin/tarifs/index.html.twig', [
            'groups' => $tarifRepository->findGroupedByCategorie(),
        ]);
    }

    #[Route('/ajouter', name: 'admin_tarif_add', methods: ['POST'])]
    public function add(Request $request, TarifRepository $tarifRepository, EntityManagerInterface $em): Response
    {
        $tarif = new Tarif();
        if (!$this->hydrate($tarif, $request)) {
            $this->addFlash('error', 'Catégorie et libellé sont obligatoires.');

            return $this->redirectToRoute('admin_tarifs');
        }

        $last = $tarifRepository->findOneBy([], ['position' => 'DESC']);
        $tarif->setPosition($last ? $last->getPosition() + 1 : 0);

        $em->persist($tarif);
        $em->flush();

        $this->addFlash('success', 'Tarif ajouté.');

        return $this->redirectToRoute('admin_tarifs');
    }

    #[Route('/{id}/modifier', name: 'admin_tarif_edit', methods: ['POST'])]
    public function edit(Tarif $tarif, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->hydrate($tarif, $request)) {
            $this->addFlash('error', 'Catégorie et libellé sont obligatoires.');

            return $this->redirectToRoute('admin_tarifs');
        }

        $em->flush();
        $this->addFlash('success', 'Tarif mis à jour.');

        return $this->redirectToRoute('admin_tarifs');
    }

    #[Route('/{id}/supprimer', name: 'admin_tarif_delete', methods: ['POST'])]
    public function delete(Tarif $tarif, EntityManagerInterface $em): Response
    {
        $em->remove($tarif);
        $em->flush();

        $this->addFlash('success', 'Tarif supprimé.');

        return $this->redirectToRoute('admin_tarifs');
    }

    private function hydrate(Tarif $tarif, Request $request): bool
    {
        $categorie = trim((string) $request->request->get('categorie', $tarif->getCategorie()));
        $libelle = trim((string) $request->request->get('libelle'));

        if ($categorie === '' || $libelle === '') {
            return false;
        }

        $prix = str_replace(',', '.', (string) $request->request->get('prix', '0'));
        $unite = trim((string) $request->request->get('unite'));
        $description = trim((string) $request->request->get('description'));

        $tarif->setCategorie($categorie);
        $tarif->setLibelle($libelle);
        $tarif->setDescription($description !== '' ? $description : null);
        $tarif->setPrix(max(0, (float) $prix));
        $tarif->setUnite($unite !== '' ? $unite : null);
        $tarif->setActif($request->request->getBoolean('actif'));

        return true;
    }
}
