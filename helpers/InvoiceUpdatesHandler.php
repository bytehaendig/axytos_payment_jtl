<?php

namespace Plugin\axytos_payment\helpers;

use Plugin\axytos_payment\helpers\CSVHelper;
use Plugin\axytos_payment\helpers\Utils;
use JTL\DB\DbInterface;

class InvoiceUpdatesHandler
{
    private $paymentMethod;
    private DbInterface $db;

    public function __construct($paymentMethod, DbInterface $db)
    {
        $this->paymentMethod = $paymentMethod;
        $this->db = $db;
    }

    /**
     * Validates CSV structure without full parsing
     */
    public function validateCSVStructure(string $filePath, ?string $originalFilename = null): bool
    {
        $csvHelper = new CSVHelper();
        try {
            return $csvHelper->validateCsvStructure($filePath, originalFilename: $originalFilename);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('CSV validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalizes CSV data with German field names to the internal format.
     * Expected input: [['rechnungsnummer' => 'value', 'externe bestellnummer' => 'value'], ...]
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
                'invoiceNumber' => $row['rechnungsnummer'] ?? '',
                'orderNumber' => $row['externe bestellnummer'] ?? ''
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
                    'status' => 'error',
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
                    'status' => 'error',
                    'error' => 'Empty invoice number or order number'
                ];
                continue;
            }

            try {
                // Check if order already has an invoice number
                $existingInvoiceNumber = $this->getExistingInvoiceNumber($orderNumber);

                if ($existingInvoiceNumber !== null && $existingInvoiceNumber === $invoiceNumber) {
                    // Order already has the same invoice number - skip processing
                    $results[] = [
                        'invoiceNumber' => $invoiceNumber,
                        'orderNumber' => $orderNumber,
                        'status' => 'skipped'
                    ];
                    continue;
                }

                // Queue action without immediate processing (processImmediately = false)
                $this->paymentMethod->invoiceWasCreated($orderNumber, $invoiceNumber, false);

                $results[] = [
                    'invoiceNumber' => $invoiceNumber,
                    'orderNumber' => $orderNumber,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'invoiceNumber' => $invoiceNumber,
                    'orderNumber' => $orderNumber,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'results' => $results,
            'total_processed' => $totalProcessed,
            'successful_count' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success')),
            'error_count' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'error'))
        ];
    }

    /**
     * Check if an order already has an invoice number set
     */
    private function getExistingInvoiceNumber(string $orderNumber): ?string
    {
        try {
            // Load the order to get its ID
            $order = Utils::loadOrderByOrderNumber($this->db, $orderNumber);
            if ($order === null) {
                return null; // Order doesn't exist, so no invoice number
            }

            // Check for existing invoice number in order attributes
            $result = $this->db->select('tbestellattribut', 'kBestellung', (int)$order->kBestellung, 'cName', 'invoice_number');
            if ($result !== null) {
                return $result->cValue;
            }

            return null;
        } catch (\Exception $e) {
            // If there's any error checking, assume no invoice exists to be safe
            return null;
        }
    }

    /**
     * Get count of orders awaiting invoice
     */
    public function getOrdersAwaitingInvoiceCount(): int
    {
        $sql = "
            SELECT COUNT(DISTINCT kBestellung) as count
            FROM axytos_actions a1
            WHERE cAction IN ('confirm', 'shipped', 'reverse_cancel')
              AND NOT EXISTS (
                  SELECT 1 
                  FROM axytos_actions a2 
                  WHERE a2.kBestellung = a1.kBestellung 
                    AND a2.dProcessedAt > a1.dProcessedAt
              )
        ";
        
        $result = $this->db->getSingleArray($sql, []);
        return (int)($result['count'] ?? 0);
    }
}
