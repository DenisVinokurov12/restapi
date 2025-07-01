<?php

namespace App\Repository;

use App\Entity\Document;
use App\Query\GetAnalytics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{

    CONST DB_DATE_FORMAT = 'Y-m-d';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function getLastOneByProductIdOrderByReportDate(int $productId)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.product_id = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('d.report_date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAllOrderByProductIdAndReportDateAsc()
    {
        return $this->createQueryBuilder('d')
            ->addOrderBy('d.product_id', 'ASC')
            ->addOrderBy('d.report_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAnalyticsByDate(\DateTime $date): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $query = (string) new GetAnalytics();

        return $conn->executeQuery($query, [
            'reportDate' => $date->format(static::DB_DATE_FORMAT),
        ])->fetchAllAssociativeIndexed();
    }

}
