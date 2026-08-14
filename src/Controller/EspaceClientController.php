<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
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
}