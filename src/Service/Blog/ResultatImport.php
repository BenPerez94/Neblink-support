<?php

namespace App\Service\Blog;

use App\Entity\Article;

final class ResultatImport
{
    /**
     * @param string[] $avertissements
     */
    public function __construct(
        public readonly Article $article,
        public readonly bool $creation,
        public readonly array $avertissements = [],
    ) {
    }
}
