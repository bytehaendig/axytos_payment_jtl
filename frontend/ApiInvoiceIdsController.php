<?php

namespace Plugin\axytos_payment\frontend;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Plugin\axytos_payment\helpers\CSVHelper;
use Plugin\axytos_payment\helpers\InvoiceUpdatesHandler;
use Plugin\axytos_payment\helpers\SecurityHelper;

class ApiInvoiceIdsController
{
    private $plugin;
    private $paymentMethod;
    private InvoiceUpdatesHandler $invoiceUpdatesHandler;
    private SecurityHelper $securityHelper;
    private string $path = '/axytos/v1/invoice-ids';

    public function __construct($plugin, $paymentMethod, $db)
    {
        $this->plugin = $plugin;
        $this->paymentMethod = $paymentMethod;
        $this->invoiceUpdatesHandler = new InvoiceUpdatesHandler($paymentMethod, $db);

        // Initialize security helper
        $this->securityHelper = new SecurityHelper();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Authenticates webhook requests using X-Axytos-Webhook-Key header with security enhancements
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse|null Returns error response if authentication fails, null if successful
     */
    private function authenticateWebhookRequest(ServerRequestInterface $request): ?JsonResponse
    {
        $clientIP = $this->securityHelper->getClientIP();

        // Get the webhook key from plugin settings
        $expectedKey = $this->paymentMethod->getSetting('webhook_api_key');

        // Check if webhook key is configured
        if (empty($expectedKey)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook authentication not configured'
            ], 500);
        }

        // Validate minimum key length (32+ characters)
        if (strlen($expectedKey) < 32) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook key configuration error'
            ], 500);
        }

        // Get the provided key from header
        $providedKey = $request->getHeaderLine('X-Axytos-Webhook-Key');

        // Check if header is present
        if (empty($providedKey)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Missing X-Axytos-Webhook-Key header'
            ], 401);
        }

        // Validate key length
        if (strlen($providedKey) < 32) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid webhook key format'
            ], 401);
        }

        // Use hash_equals() to prevent timing attacks
        if (!hash_equals($expectedKey, $providedKey)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid webhook key'
            ], 401);
        }

        // Validate Content-Type
        $contentType = $request->getHeaderLine('Content-Type');
        $allowedTypes = ['application/json', 'text/csv'];

        $isValidContentType = false;
        foreach ($allowedTypes as $allowedType) {
            if (strpos($contentType, $allowedType) !== false) {
                $isValidContentType = true;
                break;
            }
        }

        if (!$isValidContentType) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid Content-Type. Only application/json and text/csv are allowed.'
            ], 400);
        }

        return null;
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

            // Get request body for validation
            $body = $request->getBody()->getContents();

            // Validate payload size (1MB limit)
            $payloadValidation = $this->securityHelper->validatePayloadSize($body);
            if ($payloadValidation !== null) {
                return $payloadValidation;
            }

            // Authenticate webhook request
            $authResult = $this->authenticateWebhookRequest($request);
            if ($authResult !== null) {
                return $authResult;
            }
            $contentType = $request->getHeaderLine('Content-Type');

            if (strpos($contentType, 'text/csv') === 0) {
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
