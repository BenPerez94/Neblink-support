<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/clients')]
#[IsGranted('ROLE_ADMIN')]
class ClientController extends AbstractController
{
    #[Route('', name: 'admin_clients')]
    public function index(EntityManagerInterface $em): Response
    {
        $clients = $em->getRepository(Client::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/clients/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/nouveau', name: 'admin_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $client = new Client();
            $client->setNom($request->request->get('nom'));
            $client->setEmail($request->request->get('email'));
            $client->setTelephone($request->request->get('telephone') ?: null);

            $this->generateAndSendSetupLink($client, $em, $mailer);

            $this->addFlash('success', 'Client créé, un email de création de mot de passe lui a été envoyé.');

            return $this->redirectToRoute('admin_clients');
        }

        return $this->render('admin/clients/new.html.twig');
    }

    #[Route('/{id}/renvoyer-lien', name: 'admin_client_resend_link', methods: ['POST'])]
    public function resendLink(Client $client, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $this->generateAndSendSetupLink($client, $em, $mailer);

        $this->addFlash('success', 'Nouveau lien envoyé.');

        return $this->redirectToRoute('admin_clients');
    }

    private function generateAndSendSetupLink(Client $client, EntityManagerInterface $em, MailerInterface $mailer): void
    {
        $token = bin2hex(random_bytes(32));

        $client->setPasswordSetupToken($token);
        $client->setPasswordSetupTokenExpiresAt(
            new \DateTimeImmutable('+48 hours', new \DateTimeZone('Europe/Paris'))
        );

        $em->persist($client);
        $em->flush();

        $email = (new TemplatedEmail())
            ->from('contact@neblink.fr')
            ->to($client->getEmail())
            ->subject('Créez votre mot de passe — Espace client Neblink')
            ->htmlTemplate('emails/client_password_setup.html.twig')
            ->context(['client' => $client, 'token' => $token]);

        $mailer->send($email);
    }
}