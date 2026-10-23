<?php

namespace App\Service\Blog;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Prépare le HTML d'un article avant enregistrement : nettoyage XSS, ancres sur les titres
 * (pour le sommaire), images en lazy loading, liens externes sécurisés et temps de lecture.
 */
class ContentProcessor
{
    private const MOTS_PAR_MINUTE = 200;

    public function __construct(private HtmlSanitizerInterface $appBlogSanitizer)
    {
    }

    /**
     * @return array{html: string, texte: string, sommaire: list<array{niveau: int, id: string, texte: string}>, tempsLecture: int}
     */
    public function process(string $html): array
    {
        $html = $this->appBlogSanitizer->sanitize($html);

        $document = \Dom\HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8'
        );
        $body = $document->body;

        // Espaces insécables avant la ponctuation, sauf dans le code
        $xpath = new \Dom\XPath($document);
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');
        foreach ($xpath->query('//h:body//text()[not(ancestor::h:code) and not(ancestor::h:pre)]') as $texte) {
            $texte->textContent = Typographie::espacesInsecables($texte->textContent);
        }

        $slugger = new AsciiSlugger('fr');
        $sommaire = [];
        $idsUtilises = [];

        foreach ($body->querySelectorAll('h2, h3') as $titre) {
            $texte = trim($titre->textContent);
            if ($texte === '') {
                continue;
            }

            $base = $slugger->slug($texte)->lower()->toString() ?: 'section';
            $id = $base;
            $i = 2;
            while (isset($idsUtilises[$id])) {
                $id = $base . '-' . $i++;
            }
            $idsUtilises[$id] = true;

            $titre->setAttribute('id', $id);
            $sommaire[] = [
                'niveau' => $titre->tagName === 'H2' ? 2 : 3,
                'id' => $id,
                'texte' => $texte,
            ];
        }

        foreach ($body->querySelectorAll('img') as $image) {
            // Le sanitizer retire les src dangereux : une image sans source ne sert plus à rien
            if (!$image->hasAttribute('src')) {
                $image->remove();
                continue;
            }
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
        }

        foreach ($body->querySelectorAll('a[href]') as $lien) {
            $host = parse_url($lien->getAttribute('href'), PHP_URL_HOST);
            if (is_string($host) && !str_ends_with($host, 'neblink.fr')) {
                $lien->setAttribute('target', '_blank');
                $lien->setAttribute('rel', 'noopener noreferrer');
            } else {
                $lien->removeAttribute('target');
                $lien->removeAttribute('rel');
            }
        }

        // Paragraphes vides laissés par l'éditeur (ligne finale, retours à la ligne en trop)
        foreach ($body->querySelectorAll('p') as $paragraphe) {
            if (trim($paragraphe->textContent) === '' && $paragraphe->querySelector('img') === null) {
                $paragraphe->remove();
            }
        }

        $mots = preg_split('/\s+/u', trim($body->textContent), -1, PREG_SPLIT_NO_EMPTY);

        return [
            'html' => $body->innerHTML,
            'texte' => implode(' ', $mots),
            'sommaire' => $sommaire,
            'tempsLecture' => max(1, (int) round(count($mots) / self::MOTS_PAR_MINUTE)),
        ];
    }
}
