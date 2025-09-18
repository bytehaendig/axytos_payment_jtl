<?php

namespace Plugin\axytos_payment\helpers;

use Plugin\axytos_payment\helpers\CSVHelper;

class InvoiceUpdatesHandler
{
    private $paymentMethod;

    public function __construct($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Validates CSV structure without full parsing
     */
    public function validateCSVStructure(string $filePath, ?string $originalFilename = null): bool
    {
        $csvHelper = new CSVHelper();
        try {
            return $csvHelper->validateCsvStructure($filePath, $originalFilename);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('CSV validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalizes CSV data with German field names to the internal format.
     * Expected input: [['Rechnungsnummer' => 'value', 'Externe Bestellnummer' => 'value'], ...]
     * Expected output: [['invoiceNumber' => 'value', 'orderNumber' => 'value'], ...]
     */
    public function normalizeCSV(array $data): array
    {
        if (!is_array($data) || empty($data)) {
            return [];
        }

        $normalizedRows = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalizedRows[] = [
                'invoiceNumber' => $row['Rechnungsnummer'] ?? '',
                'orderNumber' => $row['Externe Bestellnummer'] ?? ''
            ];
        }

        return $normalizedRows;
    }

    /**
     * Processes invoice IDs update
     */
    public function processInvoiceIdsUpdate(array $data): array
    {
        $results = [];
        $totalProcessed = count($data);

        foreach ($data as $row) {
            // Check if row has required fields
            if (!isset($row['invoiceNumber']) || !isset($row['orderNumber'])) {
                $results[] = [
                    'invoiceNumber' => $row['invoiceNumber'] ?? null,
                    'orderNumber' => $row['orderNumber'] ?? null,
                    'success' => false,
                    'error' => 'Missing required fields: invoiceNumber or orderNumber'
                ];
                continue;
            }

            $invoiceNumber = trim($row['invoiceNumber']);
            $orderNumber = trim($row['orderNumber']);

            // Validate that fields are not empty
            if (empty($invoiceNumber) || empty($orderNumber)) {
                $results[] = [
                    'invoiceNumber' => $invoiceNumber,
                    'orderNumber' => $orderNumber,
                    'success' => false,
                    'error' => 'Empty invoice number or order number'
                ];
                continue;
            }

            try {
                // Order lookup and invoice creation is now handled inside the method
                $this->paymentMethod->invoiceWasCreated($orderNumber, $invoiceNumber);

                $results[] = [
                    'invoiceNumber' => $invoiceNumber,
                    'orderNumber' => $orderNumber,
                    'success' => true,
                    'error' => null
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'invoiceNumber' => $invoiceNumber,
                    'orderNumber' => $orderNumber,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'results' => $results,
            'total_processed' => $totalProcessed,
            'successful_count' => count(array_filter($results, fn($r) => $r['success'])),
            'error_count' => count(array_filter($results, fn($r) => !$r['success']))
        ];
    }
}