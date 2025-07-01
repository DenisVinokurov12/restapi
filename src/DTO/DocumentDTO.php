<?php

namespace App\DTO;

use App\Config\DocumentType;
use App\Entity\Document;
use App\Repository\DocumentRepository;
use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\SerializedName;

class DocumentDTO
{

    #[SerializedName('id')]
    public ?int $requestId = null;

    #[Map(target: 'product_id')]
    public ?int $productId = null;

    #[Map(target: 'report_date')]
    public ?\DateTime $reportDate = null;

    #[Map(target: 'type')]
    public ?string $type = null;

    #[Map(target: 'value')]
    public ?int $value = null;

    #[Map(target: 'balance')]
    public ?int $balance = null;

    #[Map(target: 'price')]
    public ?float $price = null;

    #[Map(target: 'inventory_error')]
    public ?int $inventoryError = null;

    private DocumentRepository $repository;

    public function getRequestId(): ?int
    {
        return $this->requestId;
    }

    public function setRequestId(?int $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
    }

    public function getReportDate(): ?\DateTime
    {
        return $this->reportDate;
    }

    public function setReportDate(?int $reportDate): void
    {
        $this->reportDate = (new \DateTime())->setTimestamp($reportDate);
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(?int $value): void
    {
        $this->value = $value;
    }

    public function getBalance(): ?int
    {
        return $this->balance;
    }

    public function setBalance(?int $balance): void
    {
        $this->balance = $balance;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    public function getInventoryError(): ?int
    {
        return $this->inventoryError;
    }

    public function setInventoryError(?int $inventoryError): void
    {
        $this->inventoryError = $inventoryError;
    }

    public function getRepository(): DocumentRepository
    {
        return $this->repository;
    }

    public function setRepository(DocumentRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function preprocess(): void
    {
        $this->checkEmptyValues();

        if ($this->type == DocumentType::INVENTORY->value) {
            $this->balance = $this->balance ?: $this->value;

            /** @var Document $lastDocument */
            $lastDocument = $this->repository->getLastOneByProductIdOrderByReportDate($this->productId);

            if (empty($lastDocument)) {
                return;
            }

            $this->inventoryError = $this->balance - $lastDocument->getBalance();
        }
    }

    public function checkEmptyValues(): void
    {
        if (empty($this->requestId)) {
            throw new EmptyParameterValueException('empty id');
        }

        if (empty($this->productId)) {
            throw new EmptyParameterValueException('empty productId');
        }

        if (empty($this->reportDate)) {
            throw new EmptyParameterValueException('empty reportDate');
        }

        if (empty($this->type)) {
            throw new EmptyParameterValueException('empty type');
        }

        if (empty($this->value)) {
            throw new EmptyParameterValueException('empty value');
        }

        if (empty($this->balance) && $this->type !== DocumentType::INVENTORY->value) {
            throw new EmptyParameterValueException('empty balance');
        }
    }

}
