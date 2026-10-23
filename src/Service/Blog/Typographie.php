<?php

namespace App\Service\Blog;

/**
 * Typographie française : l'espace avant ? ! : ; » et après « devient insécable,
 * pour que la ponctuation ne se retrouve jamais seule en début de ligne.
 */
final class Typographie
{
    private const INSECABLE = "\u{00A0}";

    public static function espacesInsecables(string $texte): string
    {
        $texte = preg_replace('/ +([?!:;»])/u', self::INSECABLE . '$1', $texte);

        return preg_replace('/« +/u', '«' . self::INSECABLE, $texte);
    }
}
