<?php

namespace App\Repository;

use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    /**
     * @param array{dateFrom?: ?\DateTimeImmutable, dateTo?: ?\DateTimeImmutable, clientId?: ?int, statut?: ?string} $filters
     * @return Intervention[]
     */
    public function findFiltered(array $filters, string $sort, string $direction): array
    {
        $sortableColumns = [
            'date' => 'i.date',
            'client' => 'c.nom',
        ];
        $field = $sortableColumns[$sort] ?? $sortableColumns['date'];

        $qb = $this->createQueryBuilder('i')
            ->join('i.client', 'c')
            ->addSelect('c')
            ->orderBy($field, $direction);

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('i.date >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('i.date <= :dateTo')->setParameter('dateTo', $filters['dateTo']);
        }
        if (!empty($filters['clientId'])) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $filters['clientId']);
        }
        if (!empty($filters['statut'])) {
            $qb->andWhere('i.statut = :statut')->setParameter('statut', $filters['statut']);
        }

        return $qb->getQuery()->getResult();
    }
}