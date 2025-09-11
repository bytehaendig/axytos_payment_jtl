<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\helpers\CSVHelper;

/** this is currently not used */
class ToolsHandler
{
    private PluginInterface $plugin;
    private $method;
    private DbInterface $db;
    private $client;
    private CSVHelper $csvHelper;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
        $this->client = $this->method->createApiClient();
        $this->csvHelper = new CSVHelper();
    }

    public function render(string $tabname, int $menuID, JTLSmarty $smarty): string
    {
        // Initialize messages array
        $messages = [];

        // Handle form submission
        if (Request::postInt('save_tools') === 1 && Form::validateToken()) {
            // activate the current tab
            $smarty->assign('defaultTabbertab', $menuID);
            $results = $this->handleCsvUpload();
            $messages = array_merge($messages, $results);
        }

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('token', Form::getTokenInput());
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/tools.tpl');
    }

    private function handleCsvUpload(): array
    {
        $messages = [];

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            return [['type' => 'danger', 'text' => 'Error uploading file or no file selected.']];
        }

        $csvFile = $_FILES['csv_file']['tmp_name'];
        $csvData = $this->csvHelper->parseCsv($csvFile);

        if (empty($csvData)) {
            return [['type' => 'warning', 'text' => 'No valid data found in CSV file.']];
        }

        $processingResult = $this->processCsvData($csvData);
        $processedCount = $processingResult['processedCount'];
        $results = $processingResult['results'];

        $messages[] = ['type' => 'success', 'text' => "Successfully processed {$processedCount} IDs from CSV file."];
        foreach ($results as $result) {
            $statusClass = $result['status'] === 'success' ? 'success' : 'warning';
            $messages[] = [
                'type' => $statusClass,
                'text' => "ID: {$result['id']} (Timestamp: {$result['timestamp']}) - Status: {$result['status']}"
            ];
        }

        return $messages;
    }

    private function processCsvData(array $csvData): array
    {
        $processedCount = 0;
        $results = [];

        foreach ($csvData as $row) {
            // Look for common ID field names (case-insensitive)
            $idField = $this->findIdField($row);
            $timestampField = $this->findTimestampField($row);

            if ($idField && !empty($row[$idField])) {
                $timestamp = $timestampField ? $row[$timestampField] : '';
                $status = $this->processId($row[$idField], $timestamp);
                $results[] = [
                    'id' => $row[$idField],
                    'timestamp' => $timestamp,
                    'status' => $status
                ];
                $processedCount++;
            }
        }

        return [
            'processedCount' => $processedCount,
            'results' => $results
        ];
    }

    private function findIdField(array $row): ?string
    {
        $possibleIdFields = ['id', 'order_id', 'orderid', 'reference', 'reference_id'];
        foreach ($possibleIdFields as $field) {
            if (isset($row[$field])) {
                return $field;
            }
        }
        return null;
    }

    private function findTimestampField(array $row): ?string
    {
        $possibleTimestampFields = ['timestamp', 'date', 'created_at', 'time'];
        foreach ($possibleTimestampFields as $field) {
            if (isset($row[$field])) {
                return $field;
            }
        }
        return null;
    }

    private function processId(string $orderId, string $timestamp): string
    {
        error_log("Processing ID: {$orderId} with timestamp: {$timestamp}");
        return 'success';
    }
}
