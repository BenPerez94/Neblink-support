<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messages')]
#[IsGranted('ROLE_ADMIN')]
class MessageController extends AbstractController
{
    #[Route('', name: 'admin_messages')]
    public function index(EntityManagerInterface $em): Response
    {
        $contacts = $em->getRepository(Contact::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/messages/index.html.twig', [
            'contacts' => $contacts,
            'nouveauxMessagesCount' => $em->getRepository(Contact::class)->count(['statut' => 'nouveau']),
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_message_update_statut', methods: ['POST'])]
    public function updateStatut(Contact $contact, Request $request, EntityManagerInterface $em): Response
    {
        $statut = $request->request->get('statut');

        if (in_array($statut, ['nouveau', 'vu', 'en_cours', 'traite'], true)) {
            $contact->setStatut($statut);
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('admin_messages');
    }

    #[Route('/{id}/delete', name: 'admin_message_delete', methods: ['POST'])]
    public function delete(Contact $contact, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-message-' . $contact->getId(), $request->request->get('_token'))) {
            $em->remove($contact);
            $em->flush();
            $this->addFlash('success', 'Message supprimé.');
        }

        return $this->redirectToRoute('admin_messages');
    }
}