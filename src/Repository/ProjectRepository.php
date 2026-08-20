<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @param array{dateFrom?: ?\DateTimeImmutable, dateTo?: ?\DateTimeImmutable, clientId?: ?int, statut?: ?string} $filters
     * @return Project[]
     */
    public function findFiltered(array $filters, string $sort, string $direction): array
    {
        $sortableColumns = [
            'date' => 'p.createdAt',
            'client' => 'c.nom',
        ];
        $field = $sortableColumns[$sort] ?? $sortableColumns['date'];

        $qb = $this->createQueryBuilder('p')
            ->join('p.client', 'c')
            ->addSelect('c')
            ->orderBy($field, $direction);

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('p.createdAt >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('p.createdAt <= :dateTo')->setParameter('dateTo', $filters['dateTo']);
        }
        if (!empty($filters['clientId'])) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $filters['clientId']);
        }
        if (!empty($filters['statut'])) {
            $qb->andWhere('p.statut = :statut')->setParameter('statut', $filters['statut']);
        }

        return $qb->getQuery()->getResult();
    }
}