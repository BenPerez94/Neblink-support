<?php

namespace App\Service\Blog;

use App\Entity\Article;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Image utilisée pour les aperçus de partage (Facebook, LinkedIn, WhatsApp…) :
 * la couverture recadrée en 1200 × 630 et compressée sous 200 Ko.
 * Elle est générée à la première demande, puis réutilisée.
 */
class ImagePartage
{
    public const LARGEUR = 1200;
    public const HAUTEUR = 630;
    private const POIDS_MAX = 200 * 1024;
    private const DOSSIER_PUBLIC = '/uploads/blog/partage';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Chemin public de l'image de partage de l'article, ou null s'il n'a pas de couverture
     * (ou si l'image n'a pas pu être générée).
     */
    public function cheminPublic(Article $article, bool $regenerer = false): ?string
    {
        if (!$article->getCouverture()) {
            return null;
        }

        $source = $this->projectDir . '/public/uploads/blog/' . $article->getCouverture();
        $nom = pathinfo($article->getCouverture(), PATHINFO_FILENAME) . '.jpg';
        $destination = $this->projectDir . '/public' . self::DOSSIER_PUBLIC . '/' . $nom;

        if (!is_file($destination) || $regenerer) {
            try {
                $this->generer($source, $destination);
            } catch (\Throwable $e) {
                $this->logger->warning('Image de partage non générée pour l\'article {slug} : {erreur}', [
                    'slug' => $article->getSlug(),
                    'erreur' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return self::DOSSIER_PUBLIC . '/' . $nom;
    }

    /**
     * Recadre l'image au centre en 1200 × 630 et l'enregistre en JPEG progressif sous 200 Ko.
     */
    public function generer(string $source, string $destination): void
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('L\'extension PHP GD n\'est pas activée.');
        }
        if (!is_file($source)) {
            throw new \RuntimeException(sprintf('Image source introuvable : %s', $source));
        }

        $image = imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            throw new \RuntimeException(sprintf('Image source illisible : %s', $source));
        }
        $image = $this->corrigerOrientation($image, $source);

        // Recadrage centré au format 1200 × 630 (« object-fit: cover »)
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $ratioCible = self::LARGEUR / self::HAUTEUR;
        if ($largeur / $hauteur > $ratioCible) {
            $largeurCoupe = (int) round($hauteur * $ratioCible);
            $hauteurCoupe = $hauteur;
        } else {
            $largeurCoupe = $largeur;
            $hauteurCoupe = (int) round($largeur / $ratioCible);
        }
        $x = (int) (($largeur - $largeurCoupe) / 2);
        $y = (int) (($hauteur - $hauteurCoupe) / 2);

        $sortie = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        // Fond blanc pour les PNG transparents
        imagefill($sortie, 0, 0, (int) imagecolorallocate($sortie, 255, 255, 255));
        imagecopyresampled($sortie, $image, 0, 0, $x, $y, self::LARGEUR, self::HAUTEUR, $largeurCoupe, $hauteurCoupe);
        imageinterlace($sortie, true);

        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0775, true);
        }

        // On baisse la qualité jusqu'à passer sous le poids maximum
        foreach ([82, 75, 68, 60, 50] as $qualite) {
            imagejpeg($sortie, $destination, $qualite);
            clearstatcache(true, $destination);
            if (filesize($destination) <= self::POIDS_MAX) {
                break;
            }
        }
    }

    /**
     * Les photos de téléphone sont souvent enregistrées couchées avec une indication de rotation (EXIF).
     */
    private function corrigerOrientation(\GdImage $image, string $source): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
