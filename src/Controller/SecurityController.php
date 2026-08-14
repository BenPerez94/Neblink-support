<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setPasswordResetToken($token);
                $user->setPasswordResetTokenExpiresAt(
                    new \DateTimeImmutable('+2 hours', new \DateTimeZone('Europe/Paris'))
                );
                $em->flush();

                $emailMessage = (new TemplatedEmail())
                    ->from('contact@neblink.fr')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe — Admin Neblink')
                    ->htmlTemplate('emails/admin_password_reset.html.twig')
                    ->context(['user' => $user, 'token' => $token]);

                $mailer->send($emailMessage);
            }

            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de réinitialisation vient de vous être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['passwordResetToken' => $token]);

        if (!$user || $user->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))) {
            return $this->render('security/lien_expire.html.twig');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmation = $request->request->get('password_confirmation');

            if ($password !== $confirmation || strlen($password) < 8) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas ou sont trop courts (8 caractères minimum).');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour, vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}
