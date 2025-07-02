<?php

namespace App\Repository;

use App\DTO\DocumentDTO;
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

    CONST DB_DATETIME_FORMAT = 'Y-m-d H:i:s';
    CONST DB_DATE_FORMAT = 'Y-m-d';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function getLastOneByProductIdAndReportDateLessOrderByReportDate(int $productId, \DateTime $reportDate)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.product_id = :productId')
            ->andWhere('d.report_date <= :reportDate')
            ->setParameter('productId', $productId)
            ->setParameter('reportDate', $reportDate->format(static::DB_DATETIME_FORMAT))
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

    public function getAllNextDocuments(Document $document)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.product_id = :product')
            ->andWhere('d.report_date > :reportDate')
            ->setParameter('product', $document->getProductId())
            ->setParameter('reportDate', $document->getReportDate()->format(static::DB_DATETIME_FORMAT))
            ->orderBy('d.report_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
