<?php

namespace Plugin\axytos_payment\frontend;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Plugin\axytos_payment\helpers\CSVHelper;
use Plugin\axytos_payment\helpers\InvoiceUpdatesHandler;

class ApiInvoiceIdsController
{
    private $plugin;
    private $paymentMethod;
    private InvoiceUpdatesHandler $invoiceUpdatesHandler;
    private string $path = '/axytos/v1/invoice-ids';

    public function __construct($plugin, $paymentMethod)
    {
        $this->plugin = $plugin;
        $this->paymentMethod = $paymentMethod;
        $this->invoiceUpdatesHandler = new InvoiceUpdatesHandler($paymentMethod);
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
                $tempFile = tempnam(sys_get_temp_dir(), 'csv_upload');
                file_put_contents($tempFile, $body);

                try {
                    // Validate CSV structure first
                    $this->invoiceUpdatesHandler->validateCSVStructure($tempFile);

                    // Parse and normalize CSV data
                    $csvHelper = new CSVHelper();
                    $data = $csvHelper->parseCsv($tempFile);
                    $data = $this->invoiceUpdatesHandler->normalizeCSV($data);
                } catch (\Exception $e) {
                    unlink($tempFile);
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Invalid CSV: ' . $e->getMessage()
                    ], 400);
                }

                unlink($tempFile);
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
            $result = $this->invoiceUpdatesHandler->processInvoiceIdsUpdate($data);

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




}
