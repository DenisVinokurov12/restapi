<?php

namespace App\Controller;

use App\Config\DocumentType;
use App\DTO\DocumentDTO;
use App\Entity\Document;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentController extends AbstractController
{

    #[Route('/documents/analytic', name: 'get_document_analytic', methods: ['GET'])]
    public function getAnalyticByDate(
        #[MapQueryParameter] int $timestamp,
        DocumentRepository $repository,
    ): JsonResponse
    {
        try {
            $analyticData = $repository->getAnalyticsByDate((new \DateTime())->setTimestamp($timestamp));
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ]);
        }

        return new JsonResponse([
            'status' => true,
            'message' => 'OK',
            'data' => $analyticData,
        ]);
    }

    #[Route('/documents/history', name: 'get_documents_history', methods: ['GET'])]
    public function getHistory(
        DocumentRepository $repository
    ): JsonResponse
    {
        $result = [];

        try {
            $allDocuments = $repository->getAllOrderByProductIdAndReportDateAsc();
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ]);
        }

        if (empty($allDocuments)) {
            return new JsonResponse([
                'status' => true,
                'message' => 'Empty Set',
                'data' => [],
            ]);
        }

        /** @var Document $document */
        foreach ($allDocuments as $document) {
            $item = [
                'report_date' => $document->getReportDate()->format('c'),
                'type' => $document->getType(),
                'value' => $document->getValue(),
                'balance' => $document->getBalance(),
            ];

            switch ($document->getType()) {
                case DocumentType::INCOMING->value:
                    $item['price'] = $document->getPrice();
                    break;
                case DocumentType::INVENTORY->value:
                    $item['inventory_error'] = $document->getInventoryError();
            }

            $result[$document->getProductId()][] = $item;
        }

        return new JsonResponse([
            'status' => true,
            'message' => 'OK',
            'data' => $result,
        ]);
    }

    #[Route('/documents', name: 'create_document', methods: ['POST'])]
    public function createDocument(
        #[MapRequestPayload(type: DocumentDTO::class)] array $documentDtos,
        EntityManagerInterface $entityManager,
        DocumentRepository $documentRepository,
        LoggerInterface $logger,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse
    {
        $badRecords = [];

        usort($documentDtos, function (DocumentDTO $a, DocumentDTO $b) {
            return $a->getReportDate()->getTimestamp() > $b->getReportDate()->getTimestamp();
        });

        /** @var DocumentDTO $documentDto */
        foreach ($documentDtos as $documentDto) {
            try {
                $documentDto->setRepository($documentRepository);
                $documentDto->preprocess();

                $document = $objectMapper->map($documentDto, Document::class);
                $entityManager->persist($document);
                $entityManager->flush();
            } catch (EmptyParameterValueException $exception) {
                $badRecords[] = [
                    'id' => $documentDto->getRequestId(),
                    'message' => $exception->getMessage(),
                ];

                continue;
            } catch (\Exception $exception) {
                $logger->error($exception->getMessage());

                return new JsonResponse([
                    'status' => false,
                    'message' => 'Internal server error (more information in log\'s)',
                    'data' => [],
                ]);
            }
        }

        usort($badRecords, function (array $a, array $b) {
            return $a['id'] > $b['id'];
        });

        return new JsonResponse([
            'status' => empty($badRecords),
            'message' => !empty($badRecords) ? 'Data passed with errors' : 'Document created successfully',
            'data' => $badRecords,
        ]);
    }

}
