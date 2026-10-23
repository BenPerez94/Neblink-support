<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Articles visibles publiquement : statut publié et date de publication passée.
     */
    private function createPublishedQueryBuilder(string $alias = 'a'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere($alias . '.statut = :statutPublie')
            ->andWhere($alias . '.datePublication <= :now')
            ->setParameter('statutPublie', Article::STATUT_PUBLIE)
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
            ->orderBy($alias . '.datePublication', 'DESC');
    }

    public function findLatestPublished(): ?Article
    {
        return $this->createPublishedQueryBuilder()
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Paginator<Article>
     */
    public function findPublishedPaginated(
        int $page,
        int $perPage,
        ?Category $category = null,
        ?Tag $tag = null,
        ?string $search = null,
        ?int $excludeId = null,
    ): Paginator {
        $qb = $this->createPublishedQueryBuilder()
            ->leftJoin('a.category', 'c')
            ->addSelect('c');

        if ($category !== null) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category);
        }

        if ($tag !== null) {
            $qb->andWhere(':tag MEMBER OF a.tags')->setParameter('tag', $tag);
        }

        if ($search !== null && $search !== '') {
            $term = '%' . addcslashes($search, '%_\\') . '%';
            $qb->andWhere('a.titre LIKE :search OR a.extrait LIKE :search OR a.contenu LIKE :search')
                ->setParameter('search', $term);
        }

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Articles liés : même catégorie d'abord, complétés par les tags en commun puis les plus récents.
     *
     * @return Article[]
     */
    public function findRelated(Article $article, int $limit = 3): array
    {
        $related = [];

        if ($article->getCategory() !== null) {
            $related = $this->createPublishedQueryBuilder()
                ->andWhere('a.category = :category')
                ->andWhere('a.id != :id')
                ->setParameter('category', $article->getCategory())
                ->setParameter('id', $article->getId())
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        $excludeIds = array_merge([$article->getId()], array_map(fn (Article $a) => $a->getId(), $related));

        if (count($related) < $limit && !$article->getTags()->isEmpty()) {
            $related = array_merge($related, $this->createPublishedQueryBuilder()
                ->join('a.tags', 't')
                ->andWhere('t IN (:tags)')
                ->andWhere('a.id NOT IN (:exclude)')
                ->setParameter('tags', $article->getTags())
                ->setParameter('exclude', $excludeIds)
                ->groupBy('a.id')
                ->setMaxResults($limit - count($related))
                ->getQuery()
                ->getResult());

            $excludeIds = array_merge([$article->getId()], array_map(fn (Article $a) => $a->getId(), $related));
        }

        if (count($related) < $limit) {
            $related = array_merge($related, $this->createPublishedQueryBuilder()
                ->andWhere('a.id NOT IN (:exclude)')
                ->setParameter('exclude', $excludeIds)
                ->setMaxResults($limit - count($related))
                ->getQuery()
                ->getResult());
        }

        return $related;
    }

    /**
     * @return Article[]
     */
    public function findAllPublished(?int $limit = null): array
    {
        $qb = $this->createPublishedQueryBuilder();

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{etat?: ?string, categoryId?: ?int} $filters
     * @return Article[]
     */
    public function findForAdmin(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->orderBy('a.updatedAt', 'DESC');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        switch ($filters['etat'] ?? null) {
            case 'brouillon':
                $qb->andWhere('a.statut = :brouillon')->setParameter('brouillon', Article::STATUT_BROUILLON);
                break;
            case 'programme':
                $qb->andWhere('a.statut = :publie AND (a.datePublication > :now OR a.datePublication IS NULL)')
                    ->setParameter('publie', Article::STATUT_PUBLIE)
                    ->setParameter('now', $now);
                break;
            case 'publie':
                $qb->andWhere('a.statut = :publie AND a.datePublication <= :now')
                    ->setParameter('publie', Article::STATUT_PUBLIE)
                    ->setParameter('now', $now);
                break;
        }

        if (!empty($filters['categoryId'])) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $filters['categoryId']);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublishedByCategory(): array
    {
        $rows = $this->createPublishedQueryBuilder()
            ->select('IDENTITY(a.category) AS categoryId, COUNT(a.id) AS total')
            ->andWhere('a.category IS NOT NULL')
            ->groupBy('a.category')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'categoryId');
    }
}
