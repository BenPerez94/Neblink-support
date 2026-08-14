<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class EspaceClientController extends AbstractController
{
    #[Route('/espace-client/login', name: 'espace_client_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('espace_client/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/espace-client/logout', name: 'espace_client_logout')]
    public function logout(): void
    {
        // intercepté par le firewall, jamais exécuté
    }

    #[Route('/espace-client/creer-mot-de-passe/{token}', name: 'espace_client_password_setup')]
    public function setupPassword(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $client = $em->getRepository(Client::class)->findOneBy(['passwordSetupToken' => $token]);

        if (!$client || $client->getPasswordSetupTokenExpiresAt() < new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))) {
            return $this->render('espace_client/lien_expire.html.twig');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmation = $request->request->get('password_confirmation');

            if ($password !== $confirmation || strlen($password) < 8) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas ou sont trop courts (8 caractères minimum).');
                return $this->render('espace_client/setup_password.html.twig', ['token' => $token]);
            }

            $client->setPassword($passwordHasher->hashPassword($client, $password));
            $client->setPasswordSetupToken(null);
            $client->setPasswordSetupTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été créé, vous pouvez vous connecter.');

            return $this->redirectToRoute('espace_client_login');
        }

        return $this->render('espace_client/setup_password.html.twig', ['token' => $token]);
    }

    #[Route('/espace-client', name: 'espace_client_dashboard')]
    #[IsGranted('ROLE_CLIENT')]
    public function dashboard(): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        return $this->render('espace_client/dashboard.html.twig', [
            'client' => $client,
            'projects' => $client->getProjects(),
        ]);
    }


    #[Route('/espace-client/mot-de-passe-oublie', name: 'espace_client_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $client = $em->getRepository(Client::class)->findOneBy(['email' => $email]);

            if ($client) {
                $token = bin2hex(random_bytes(32));
                $client->setPasswordResetToken($token);
                $client->setPasswordResetTokenExpiresAt(
                    new \DateTimeImmutable('+2 hours', new \DateTimeZone('Europe/Paris'))
                );
                $em->flush();

                $emailMessage = (new TemplatedEmail())
                    ->from('contact@neblink.fr')
                    ->to($client->getEmail())
                    ->subject('Réinitialisation de votre mot de passe — Neblink')
                    ->htmlTemplate('emails/client_password_reset.html.twig')
                    ->context(['client' => $client, 'token' => $token]);

                $mailer->send($emailMessage);
            }

            // Message identique que le client existe ou non, pour ne pas révéler
            // quels emails sont enregistrés
            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de réinitialisation vient de vous être envoyé.');

            return $this->redirectToRoute('espace_client_login');
        }

        return $this->render('espace_client/forgot_password.html.twig');
    }

    #[Route('/espace-client/reinitialiser-mot-de-passe/{token}', name: 'espace_client_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $client = $em->getRepository(Client::class)->findOneBy(['passwordResetToken' => $token]);

        if (!$client || $client->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))) {
            return $this->render('espace_client/lien_expire.html.twig');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmation = $request->request->get('password_confirmation');

            if ($password !== $confirmation || strlen($password) < 8) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas ou sont trop courts (8 caractères minimum).');
                return $this->render('espace_client/reset_password.html.twig', ['token' => $token]);
            }

            $client->setPassword($passwordHasher->hashPassword($client, $password));
            $client->setPasswordResetToken(null);
            $client->setPasswordResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour, vous pouvez vous connecter.');

            return $this->redirectToRoute('espace_client_login');
        }

        return $this->render('espace_client/reset_password.html.twig', ['token' => $token]);
    }
}