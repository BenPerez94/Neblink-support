<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BlogExtension extends AbstractExtension
{
    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', [$this, 'dateFr']),
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
}
