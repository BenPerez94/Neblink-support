<?php

namespace App\Service;

use App\Entity\Devis;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class DevisPdfGenerator
{
    /**
     * @param array<string, string> $emetteur
     */
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%app.devis_emetteur%')]
        private readonly array $emetteur,
        #[Autowire('%app.devis_validite_jours%')]
        private readonly int $validiteJours,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function generate(Devis $devis): string
    {
        $project = $devis->getProject();

        $logoPath = $this->projectDir . '/public/images/logo.svg';
        $logo = is_file($logoPath)
            ? 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($logoPath))
            : null;

        $html = $this->twig->render('admin/devis/pdf.html.twig', [
            'devis' => $devis,
            'client' => $project->getClient(),
            'project' => $project,
            'emetteur' => $this->emetteur,
            'validiteJours' => $this->validiteJours,
            'logo' => $logo,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
