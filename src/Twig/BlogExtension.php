<?php

namespace App\Twig;

use App\Repository\ArticleRepository;
use App\Service\Blog\Typographie;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BlogExtension extends AbstractExtension
{
    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    /** Une lettre de la recherche trouve aussi ses variantes accentuées, comme la base de données. */
    private const VARIANTES = [
        'a' => 'aàâä', 'c' => 'cç', 'e' => 'eéèêë', 'i' => 'iîï', 'o' => 'oôö', 'u' => 'uùûü', 'y' => 'yÿ',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', [$this, 'dateFr']),
            new TwigFilter('surligner', [$this, 'surligner'], ['is_safe' => ['html']]),
            new TwigFilter('typo', [Typographie::class, 'espacesInsecables']),
        ];
    }

    /**
     * "12 octobre 2026" sans dépendre de l'extension PHP intl.
     */
    public function dateFr(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        return sprintf('%d %s %s', (int) $date->format('j'), self::MOIS[(int) $date->format('n')], $date->format('Y'));
    }

    /**
     * Échappe le texte et entoure de <mark> les mots de la recherche (casse et accents ignorés).
     */
    public function surligner(?string $texte, ?string $recherche): string
    {
        $texte ??= '';
        $mots = ArticleRepository::motsRecherche($recherche);

        if ($mots === []) {
            return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
        }

        // Les mots les plus longs d'abord, pour qu'un mot court ne coupe pas un mot long
        usort($mots, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $motif = '/(' . implode('|', array_map([$this, 'motifMot'], $mots)) . ')/iu';

        $morceaux = preg_split($motif, $texte, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($morceaux as $i => $morceau) {
            $echappe = htmlspecialchars($morceau, ENT_QUOTES, 'UTF-8');
            // Avec DELIM_CAPTURE, les indices impairs sont les correspondances
            $html .= $i % 2 === 1 ? '<mark class="blog-mark">' . $echappe . '</mark>' : $echappe;
        }

        return $html;
    }

    private function motifMot(string $mot): string
    {
        $motif = '';
        foreach (mb_str_split(mb_strtolower($mot)) as $lettre) {
            $base = $this->lettreSansAccent($lettre);
            $motif .= isset(self::VARIANTES[$base]) ? '[' . self::VARIANTES[$base] . ']' : preg_quote($lettre, '/');
        }

        return $motif;
    }

    private function lettreSansAccent(string $lettre): string
    {
        foreach (self::VARIANTES as $base => $variantes) {
            if (str_contains($variantes, $lettre)) {
                return $base;
            }
        }

        return $lettre;
    }
}
