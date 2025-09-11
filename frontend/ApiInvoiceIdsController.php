<?php

namespace Plugin\axytos_payment\frontend;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Plugin\axytos_payment\helpers\CSVHelper;

class ApiInvoiceIdsController
{
    private $plugin;
    private $paymentMethod;
    private string $path = '/axytos/v1/invoice-ids';

    public function __construct($plugin, $paymentMethod)
    {
        $this->plugin = $plugin;
        $this->paymentMethod = $paymentMethod;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getResponse(ServerRequestInterface $request, array $args, $smarty)
    {
        try {
            // Only allow POST requests
            if ($request->getMethod() !== 'POST') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Method not allowed. Only POST requests are accepted.'
                ], 405);
            }

            // Get request body and determine content type
            $body = $request->getBody()->getContents();
            $contentType = $request->getHeaderLine('Content-Type');

            if (strpos($contentType, 'text/csv') !== false) {
                // Handle CSV input
                $csvHelper = new CSVHelper();
                $tempFile = tempnam(sys_get_temp_dir(), 'csv_upload');
                file_put_contents($tempFile, $body);

                try {
                    $data = $csvHelper->parseCsv($tempFile);
                } catch (\Exception $e) {
                    unlink($tempFile);
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Invalid CSV format: ' . $e->getMessage()
                    ], 400);
                }

                unlink($tempFile);

                // Normalize CSV data to internal format
                $data = $this->normalizeCSV($data);
            } else {
                // Handle JSON input (default)
                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Invalid JSON in request body'
                    ], 400);
                }

                // For JSON input, assume it's already in the expected format
                // If normalization is needed for JSON, it can be added here
            }

            // Process the invoice IDs update
            $result = $this->processInvoiceIdsUpdate($data);

            return new JsonResponse([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processInvoiceIdsUpdate(array $data): array
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

    /**
     * Normalizes CSV data with German field names to the internal format.
     * Expected input: [['Rechnungsnummer' => 'value', 'Externe Bestellnummer' => 'value'], ...]
     * Expected output: [['invoiceNumber' => 'value', 'orderNumber' => 'value'], ...]
     */
    private function normalizeCSV(array $data): array
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


}
