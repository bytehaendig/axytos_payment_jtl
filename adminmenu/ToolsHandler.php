<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;

/** this is currently not used */
class ToolsHandler
{
    private PluginInterface $plugin;
    private $method;
    private DbInterface $db;
    private $client;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
        $this->client = $this->method->createApiClient();
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
        $csvData = $this->parseCsv($csvFile);

        if (empty($csvData)) {
            return [['type' => 'warning', 'text' => 'No valid data found in CSV file.']];
        }

        $processedCount = 0;
        $results = [];

        foreach ($csvData as $row) {
            if (isset($row['id']) && !empty($row['id'])) {
                $status = $this->processId($row['id'], $row['timestamp'] ?? '');
                $results[] = [
                    'id' => $row['id'],
                    'timestamp' => $row['timestamp'] ?? '',
                    'status' => $status
                ];
                $processedCount++;
            }
        }

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

    private function parseCsv(string $filePath): array
    {
        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            if ($header === false || count($header) < 2) {
                fclose($handle);
                return [];
            }
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($row) >= 2) {
                    $data[] = [
                        'timestamp' => trim($row[0]),
                        'id' => trim($row[1])
                    ];
                }
            }
            fclose($handle);
        }
        return $data;
    }

    private function processId(string $orderID, string $timestamp): string
    {
        error_log("Processing ID: {$id} with timestamp: {$timestamp}");
        return 'success';
    }
}
