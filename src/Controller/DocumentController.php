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

    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    #[Route('/documents/analytic', name: 'get_document_analytic', methods: ['GET'])]
    public function getAnalyticByDate(
        #[MapQueryParameter] int $timestamp,
    ): JsonResponse
    {
        try {
            $analyticData = $this->documentRepository->getAnalyticsByDate((new \DateTime())->setTimestamp($timestamp));
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
    public function getHistory(): JsonResponse
    {
        $result = [];

        try {
            $allDocuments = $this->documentRepository->getAllOrderByProductIdAndReportDateAsc();
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
                $documentDto->setRepository($this->documentRepository);
                $documentDto->preprocess();

                $document = $objectMapper->map($documentDto, Document::class);
                $this->entityManager->persist($document);
                $this->entityManager->flush();

                $this->recalculateNextDocuments($document);
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

    private function recalculateNextDocuments(Document $document): void
    {
        $existedDocuments = $this->documentRepository->getAllNextDocuments($document);

        if (empty($existedDocuments)) {
            return;
        }

        /** @var Document $existedDocument */
        foreach ($existedDocuments as $existedDocument) {
            switch ($document->getType()) {
                case DocumentType::INCOMING->value:
                    $this->addValue($existedDocument, $document->getValue());
                    break;
                case DocumentType::OUTCOMING->value:
                    $this->addValue($existedDocument, -$document->getValue());
                    break;
                case DocumentType::INVENTORY->value:
                    if (!isset($inventoryBalance)) {
                        $inventoryBalance = $document->getBalance();
                    }

                    $inventoryBalance = $this->recalculateBalanceOrInventoryError($existedDocument, $inventoryBalance);
            }

            $this->entityManager->persist($existedDocument);
            $this->entityManager->flush();

            if ($existedDocument->getType() === DocumentType::INVENTORY->value) {
                return;
            }
        }
    }

    private function addValue(Document $document, int $value): void
    {
        if ($document->getType() === DocumentType::INVENTORY->value) {
            $document->setInventoryError($document->getInventoryError() - $value);

            return;
        }

        $document->setBalance($document->getBalance() + $value);
    }

    private function recalculateBalanceOrInventoryError(Document $document, int $inventoryBalance): int
    {
        switch ($document->getType()) {
            case DocumentType::INCOMING->value:
                $document->setBalance($inventoryBalance + $document->getValue());
                break;
            case DocumentType::OUTCOMING->value:
                $document->setBalance($inventoryBalance - $document->getValue());
                break;
            case DocumentType::INVENTORY->value:
                $document->setInventoryError($document->getBalance() - $inventoryBalance);
        }

        return $document->getBalance();
    }

}
