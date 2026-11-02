<?php

namespace App\Repository;

use App\Entity\Tarif;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TarifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarif::class);
    }

    /**
     * Tarifs regroupés par catégorie, dans l'ordre d'affichage.
     *
     * @return array<string, Tarif[]>
     */
    public function findGroupedByCategorie(bool $onlyActifs = false): array
    {
        $criteria = $onlyActifs ? ['actif' => true] : [];
        $tarifs = $this->findBy($criteria, ['position' => 'ASC', 'id' => 'ASC']);

        $grouped = [];
        foreach ($tarifs as $tarif) {
            $grouped[$tarif->getCategorie()][] = $tarif;
        }

        return $grouped;
    }
}
