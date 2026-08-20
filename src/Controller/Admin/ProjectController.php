<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ProjectStep;
use App\Entity\Document;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;


#[Route('/admin/projets')]
#[IsGranted('ROLE_ADMIN')]
class ProjectController extends AbstractController
{
    #[Route('', name: 'admin_projects', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository, ClientRepository $clientRepository): Response
    {
        $sort = $request->query->get('sort', 'date');
        $direction = strtolower($request->query->get('direction', 'desc'));

        if (!in_array($sort, ['date', 'client'], true)) {
            $sort = 'date';
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');
        $clientId = $request->query->get('clientId', '');
        $statut = $request->query->get('statut', '');

        if (!in_array($statut, ['', 'en_attente', 'en_cours', 'termine'], true)) {
            $statut = '';
        }

        $tz = new \DateTimeZone('Europe/Paris');

        try {
            $dateFromParsed = $dateFrom !== '' ? new \DateTimeImmutable($dateFrom, $tz) : null;
        } catch (\Exception) {
            $dateFrom = '';
            $dateFromParsed = null;
        }

        try {
            $dateToParsed = $dateTo !== '' ? new \DateTimeImmutable($dateTo . ' 23:59:59', $tz) : null;
        } catch (\Exception) {
            $dateTo = '';
            $dateToParsed = null;
        }

        $filters = [
            'dateFrom' => $dateFromParsed,
            'dateTo' => $dateToParsed,
            'clientId' => $clientId !== '' ? (int) $clientId : null,
            'statut' => $statut !== '' ? $statut : null,
        ];

        return $this->render('admin/projects/index.html.twig', [
            'projects' => $projectRepository->findFiltered($filters, $sort, $direction),
            'sort' => $sort,
            'direction' => $direction,
            'clients' => $clientRepository->findBy([], ['nom' => 'ASC']),
            'selectedClient' => $filters['clientId'] ? $clientRepository->find($filters['clientId']) : null,
            'filterValues' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'clientId' => $clientId,
                'statut' => $statut,
            ],
        ]);
    }

    #[Route('/nouveau/{clientId}', name: 'admin_project_new', methods: ['GET', 'POST'])]
    public function new(int $clientId, Request $request, EntityManagerInterface $em): Response
    {
        $client = $em->getRepository(Client::class)->find($clientId);
        if (!$client) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $project = new Project();
            $project->setTitre($request->request->get('titre'));
            $project->setDescription($request->request->get('description') ?: null);
            $project->setClient($client);

            $em->persist($project);
            $em->flush();

            $this->addFlash('success', 'Projet créé.');

            return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
        }

        return $this->render('admin/projects/new.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_project_edit', methods: ['GET', 'POST'])]
    public function edit(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $titre = trim((string) $request->request->get('titre'));
            if ($titre === '') {
                $this->addFlash('error', 'Le titre du projet est obligatoire.');

                return $this->render('admin/projects/edit.html.twig', [
                    'project' => $project,
                ]);
            }

            $project->setTitre($titre);
            $project->setDescription($request->request->get('description') ?: null);
            $em->flush();

            $this->addFlash('success', 'Projet modifié.');

            return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
        }

        return $this->render('admin/projects/edit.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_project_delete', methods: ['POST'])]
    public function delete(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $clientId = $project->getClient()->getId();
        $em->remove($project);
        $em->flush();

        $this->addFlash('success', 'Projet supprimé.');

        $referer = $request->headers->get('referer');
        if ($referer && str_contains((string) parse_url($referer, PHP_URL_PATH), '/admin/projets')) {
            return $this->redirectToRoute('admin_projects');
        }

        return $this->redirectToRoute('admin_client_show', ['id' => $clientId]);
    }

    #[Route('/{id}', name: 'admin_project_show')]
    public function show(Project $project): Response
    {
        return $this->render('admin/projects/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_project_update_statut', methods: ['POST'])]
    public function updateStatut(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $statut = $request->request->get('statut');

        if (in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
            $project->setStatut($statut);
            $em->flush();
        }

        return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id}/etapes/ajouter', name: 'admin_project_step_add', methods: ['POST'])]
    public function addStep(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $titre = $request->request->get('titre');

        if ($titre) {
            $topLevelCount = $project->getSteps()->filter(fn (ProjectStep $s) => $s->getParent() === null)->count();

            $step = new ProjectStep();
            $step->setTitre($titre);
            $step->setProject($project);
            $step->setPosition($topLevelCount);

            $em->persist($step);
            $em->flush();
        }

        return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
    }

    #[Route('/etapes/{id}/sous-etapes/ajouter', name: 'admin_project_substep_add', methods: ['POST'])]
    public function addSubStep(ProjectStep $parentStep, Request $request, EntityManagerInterface $em): Response
    {
        $titre = $request->request->get('titre');

        if ($titre) {
            $subStep = new ProjectStep();
            $subStep->setTitre($titre);
            $subStep->setProject($parentStep->getProject());
            $subStep->setParent($parentStep);
            $subStep->setPosition($parentStep->getChildren()->count());

            $em->persist($subStep);
            $em->flush();
        }

        return $this->redirectToRoute('admin_project_show', ['id' => $parentStep->getProject()->getId()]);
    }

    #[Route('/etapes/{id}/modifier', name: 'admin_project_step_edit', methods: ['POST'])]
    public function editStep(ProjectStep $step, Request $request, EntityManagerInterface $em): Response
    {
        $titre = trim((string) $request->request->get('titre'));

        if ($titre !== '') {
            $step->setTitre($titre);
            $step->setDescription($request->request->get('description') ?: null);
            $em->flush();
        }

        return $this->redirectToRoute('admin_project_show', ['id' => $step->getProject()->getId()]);
    }

    #[Route('/etapes/{id}/toggle', name: 'admin_project_step_toggle', methods: ['POST'])]
    public function toggleStep(ProjectStep $step, EntityManagerInterface $em): Response
    {
        $step->setTermine(!$step->isTermine());

        $parent = $step->getParent();
        if ($parent !== null && $parent->getChildren()->count() > 0) {
            $allChildrenDone = true;
            foreach ($parent->getChildren() as $child) {
                if (!$child->isTermine()) {
                    $allChildrenDone = false;
                    break;
                }
            }
            $parent->setTermine($allChildrenDone);
        }

        $em->flush();

        return $this->redirectToRoute('admin_project_show', ['id' => $step->getProject()->getId()]);
    }

    #[Route('/etapes/{id}/supprimer', name: 'admin_project_step_delete', methods: ['POST'])]
    public function deleteStep(ProjectStep $step, EntityManagerInterface $em): Response
    {
        $projectId = $step->getProject()->getId();
        $em->remove($step);
        $em->flush();

        return $this->redirectToRoute('admin_project_show', ['id' => $projectId]);
    }

    #[Route('/{id}/documents/ajouter', name: 'admin_project_document_add', methods: ['POST'])]
    public function addDocument(Project $project, Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $file = $request->files->get('fichier');
        $nom = $request->request->get('nom');
        $type = $request->request->get('type');

        if ($file && $nom && in_array($type, ['devis', 'facture'], true)) {
            $document = new Document();
            $document->setNom($nom);
            $document->setType($type);
            $document->setProject($project);
            $document->setFichierFile($file);

            $montant = $request->request->get('montant');
            if ($montant !== null && $montant !== '') {
                $document->setMontant((float) $montant);
            }

            if ($type === 'facture') {
                $document->setStatutPaiement('en_attente');
            }

            $em->persist($document);
            $em->flush();

            $this->notifyClient(
                $project,
                'Un nouveau document (' . $document->getType() . ') vient d\'être ajouté à votre projet : "' . $document->getNom() . '".',
                $mailer
            );

            $this->addFlash('success', 'Document ajouté.');
        }

        return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
    }

    #[Route('/documents/{id}/supprimer', name: 'admin_project_document_delete', methods: ['POST'])]
    public function deleteDocument(Document $document, EntityManagerInterface $em): Response
    {
        $projectId = $document->getProject()->getId();
        $em->remove($document);
        $em->flush();

        return $this->redirectToRoute('admin_project_show', ['id' => $projectId]);
    }

    #[Route('/documents/{id}/paiement', name: 'admin_project_document_payment', methods: ['POST'])]
    public function togglePayment(Document $document, EntityManagerInterface $em): Response
    {
        $document->setStatutPaiement($document->getStatutPaiement() === 'payee' ? 'en_attente' : 'payee');
        $em->flush();

        return $this->redirectToRoute('admin_project_show', ['id' => $document->getProject()->getId()]);
    }

    private function notifyClient(Project $project, string $message, MailerInterface $mailer): void
    {
        $client = $project->getClient();

        $email = (new TemplatedEmail())
            ->from('contact@neblink.fr')
            ->to($client->getEmail())
            ->subject('Nouveauté sur votre projet — Neblink')
            ->htmlTemplate('emails/client_project_notification.html.twig')
            ->context([
                'client' => $client,
                'project' => $project,
                'message' => $message,
            ]);

        $mailer->send($email);
    }
}