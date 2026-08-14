<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class PageController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }

    #[Route('/developpement-web', name: 'app_dev_web')]
    public function devWeb(): Response
    {
        return $this->render('page/dev_web.html.twig');
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($contact);
            $em->flush();

            $email = (new TemplatedEmail())
                ->from('contact@neblink.fr')
                ->to('contact@neblink.fr')
                ->subject('Nouvelle demande de contact — ' . $contact->getNom())
                ->htmlTemplate('emails/contact_notification.html.twig')
                ->context(['contact' => $contact]);

            $mailer->send($email);

            $this->addFlash('success', 'Votre message a bien été envoyé, je vous réponds rapidement.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('page/contact.html.twig', [
            'contactForm' => $form,
        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('page/mentions-legales.html.twig');
    }

    #[Route('/politique-de-confidentialite', name: 'app_confidentialite')]
    public function confidentialite(): Response
    {
        return $this->render('page/confidentialite.html.twig');
    }
}
