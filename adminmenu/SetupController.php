<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Shop;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\helpers\AutomationHandler;

class SetupController
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
    }

    public function render(string $tabname, int $menuId, JTLSmarty $smarty): string
    {
        // Initialize messages array
        $messages = [];

        // Handle form submission
        if (Request::postInt('save') === 1 && Form::validateToken()) {
            $apiKey = Request::postVar('api_key', '');
            $webhookApiKey = Request::postVar('webhook_api_key', '');
            $useSandbox = Request::postInt('use_sandbox', 0);
            $ok = $this->method->savePluginSettings(array(
                'api_key' => $apiKey,
                'webhook_api_key' => $webhookApiKey,
                'use_sandbox' => $useSandbox
            ));
            if ($ok) {
                // Add success message
                $messages[] = [
                    'type' => 'success',
                    'text' => 'Settings saved successfully.'
                ];
            }
        }

        $apiKey = $this->method->getSetting('api_key');
        $webhookApiKey = $this->method->getSetting('webhook_api_key');
        $useSandbox = $this->method->getSetting('use_sandbox');

        // Handle key generation request
        if (Request::postInt('generate_key') === 1 && Form::validateToken()) {
            $generatedKey = $this->generateSecureWebhookKey();
            $messages[] = [
                'type' => 'success',
                'text' => 'Secure webhook key generated successfully. Click "Save Settings" to store it.'
            ];
            // Pre-populate the webhook key field with generated key
            $webhookApiKey = $generatedKey;
        }

        // Handle automation script generation request
        if (Request::postInt('generate_script') === 1 && Form::validateToken()) {
            $automationResult = $this->handleAutomationScriptGeneration();
            if ($automationResult['success']) {
                $messages[] = [
                    'type' => 'success',
                    'text' => $automationResult['message']
                ];
                // If download was requested, exit here to send file
                if ($automationResult['download']) {
                    $this->downloadAutomationScript($automationResult['script_content'], $automationResult['filename']);
                    exit;
                }
            } else {
                $messages[] = [
                    'type' => 'error',
                    'text' => $automationResult['message']
                ];
            }
        }

        // Get webhook configuration data
        $webhookConfig = $this->getWebhookConfiguration();

        // Get automation configuration
        $automationConfig = $this->getAutomationConfiguration();

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('apiKey', $apiKey);
        $smarty->assign('webhookApiKey', $webhookApiKey);
        $smarty->assign('useSandbox', $useSandbox);
        $smarty->assign('webhookConfig', $webhookConfig);
        $smarty->assign('automationConfig', $automationConfig);
        $smarty->assign('token', Form::getTokenInput());
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/api_setup.tpl');
    }

    private function generateSecureWebhookKey(): string
    {
        $length = 64;
        $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*-_=+';
        $key = '';

        // Use random_bytes for secure generation (PHP 7.0+)
        if (function_exists('random_bytes')) {
            $bytes = random_bytes($length);
            for ($i = 0; $i < $length; $i++) {
                $key .= $charset[ord($bytes[$i]) % strlen($charset)];
            }
        } else {
            // Fallback for older PHP versions (less secure)
            for ($i = 0; $i < $length; $i++) {
                $key .= $charset[mt_rand(0, strlen($charset) - 1)];
            }
        }

        return $key;
    }

    private function getWebhookConfiguration(): array
    {
        // Get base URL for webhook endpoint
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        // Webhook endpoint
        $webhookUrl = $baseUrl . '/axytos/v1/invoice-ids';

        // Get webhook key status
        $webhookKey = $this->method->getSetting('webhook_api_key');
        $webhookConfigured = !empty($webhookKey);

        // Get recent webhook activity (last 10 entries)
        $recentActivity = $this->getRecentWebhookActivity();

        // Get webhook statistics
        $stats = $this->getWebhookStatistics();

        return [
            'webhookUrl' => $webhookUrl,
            'webhookConfigured' => $webhookConfigured,
            'recentActivity' => $recentActivity,
            'stats' => $stats,
            'examples' => $this->getWebhookExamples($webhookUrl, $webhookKey)
        ];
    }

    private function getRecentWebhookActivity(): array
    {
        // Return empty array since webhook logging is not implemented
        return [];
    }

    private function getWebhookStatistics(): array
    {
        // Return default stats since webhook logging is not implemented
        return [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'last_request' => null
        ];
    }

    private function getWebhookExamples(string $webhookUrl, string $webhookKey): array
    {
        $curlExample = "curl -X POST '{$webhookUrl}' \\
  -H 'Content-Type: application/json' \\
  -H 'X-Axytos-Webhook-Key: {$webhookKey}' \\
  -d '{
    \"data\": [
      {
        \"orderNumber\": \"ORD-123\",
        \"invoiceNumber\": \"INV-33939\"
      },
      {
        \"orderNumber\": \"ORD-124\",
        \"invoiceNumber\": \"INV-33940\"
      }
    ]
  }'";

        $phpExample = "<?php
\$webhookUrl = '{$webhookUrl}';
\$webhookKey = '{$webhookKey}';

\$data = [
    'data' => [
        [
            'orderNumber' => 'ORD-123',
            'invoiceNumber' => 'INV-33939'
        ],
        [
            'orderNumber' => 'ORD-124',
            'invoiceNumber' => 'INV-33940'
        ]
    ]
];

\$ch = curl_init();
curl_setopt(\$ch, CURLOPT_URL, \$webhookUrl);
curl_setopt(\$ch, CURLOPT_POST, true);
curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
curl_setopt(\$ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Axytos-Webhook-Key: ' . \$webhookKey
]);
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);

\$response = curl_exec(\$ch);
\$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
curl_close(\$ch);

if (\$httpCode === 200) {
    echo 'Webhook sent successfully';
} else {
    echo 'Webhook failed with status: ' . \$httpCode;
}
?>";

        $pythonExample = "import requests
import json

webhook_url = '{$webhookUrl}'
webhook_key = '{$webhookKey}'

data = {
    'data': [
        {
            'orderNumber': 'ORD-123',
            'invoiceNumber': 'INV-33939'
        },
        {
            'orderNumber': 'ORD-124',
            'invoiceNumber': 'INV-33940'
        }
    ]
}

headers = {
    'Content-Type': 'application/json',
    'X-Axytos-Webhook-Key': webhook_key
}

response = requests.post(webhook_url, json=data, headers=headers)

if response.status_code == 200:
    print('Webhook sent successfully')
    print('Response:', response.json())
else:
    print(f'Webhook failed with status: {response.status_code}')
    print('Response:', response.text)";

        return [
            'curl' => $curlExample,
            'php' => $phpExample,
            'python' => $pythonExample
        ];
    }

    /**
     * Handle automation script generation request
     */
    private function handleAutomationScriptGeneration(): array
    {
        try {
            // Validate prerequisites
            $validationResult = $this->validateAutomationPrerequisites();
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                    'download' => false
                ];
            }

            // Create automation handler
            $automationHandler = new AutomationHandler($this->method, $this->db);

            // Generate the script
            $scriptContent = $automationHandler->generateBatchScript();

            // Get schedule time from request (default to 09:00)
            $scheduleTime = Request::postVar('schedule_time', '09:00');

            // Process template variables
            $templateVars = [
                'SHOP_URL' => $this->getShopUrl(),
                'WEBHOOK_KEY' => $this->method->getSetting('webhook_api_key'),
                'SCHEDULE_TIME' => $scheduleTime,
                'TIMESTAMP' => date('Y-m-d H:i:s'),
                'PLUGIN_VERSION' => $this->plugin->getMeta()->getVersion()
            ];

            // Replace template variables
            $processedScript = $this->processTemplate($scriptContent, $templateVars);

            // Generate filename
            $filename = 'axytos_automation_' . date('Y-m-d_H-i-s') . '.bat';

            return [
                'success' => true,
                'message' => 'Automation script generated successfully. After installation, please check and edit the JTL-WaWi configuration parameters in the generated PowerShell script.',
                'script_content' => $processedScript,
                'filename' => $filename,
                'download' => true
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate automation script: ' . $e->getMessage(),
                'download' => false
            ];
        }
    }

    /**
     * Validate automation prerequisites
     */
    private function validateAutomationPrerequisites(): array
    {
        // Check if webhook key is configured
        $webhookKey = $this->method->getSetting('webhook_api_key');
        if (empty($webhookKey)) {
            return [
                'valid' => false,
                'message' => 'Webhook key must be configured before generating automation script.'
            ];
        }

        // Check if API key is configured (for validation purposes)
        $apiKey = $this->method->getSetting('api_key');
        if (empty($apiKey)) {
            return [
                'valid' => false,
                'message' => 'API key must be configured before generating automation script.'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Prerequisites validated successfully.'
        ];
    }

    /**
     * Get the shop URL for the automation script
     */
    private function getShopUrl(): string
    {
        // Get base URL for shop
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        return rtrim($baseUrl, '/');
    }

    /**
     * Process template variables in script content
     */
    private function processTemplate(string $template, array $variables): string
    {
        $processed = $template;

        foreach ($variables as $placeholder => $value) {
            $processed = str_replace('{{' . $placeholder . '}}', $value, $processed);
        }

        return $processed;
    }

    /**
     * Download automation script as file
     */
    private function downloadAutomationScript(string $content, string $filename): void
    {
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Clear any output buffers
        if (ob_get_level()) {
            ob_clean();
        }

        // Output the content
        echo $content;

        // Exit to prevent any further output
        exit;
    }

    /**
     * Get automation configuration data
     */
    private function getAutomationConfiguration(): array
    {
        // Check if automation prerequisites are met
        $validationResult = $this->validateAutomationPrerequisites();
        $automationReady = $validationResult['valid'];

        // Get current webhook key status
        $webhookKey = $this->method->getSetting('webhook_api_key');
        $webhookConfigured = !empty($webhookKey);

        // Get API key status
        $apiKey = $this->method->getSetting('api_key');
        $apiConfigured = !empty($apiKey);

        // Default schedule time
        $defaultScheduleTime = '17:00';

        // Generate schedule time options
        $scheduleOptions = [];
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                $scheduleOptions[] = [
                    'value' => $time,
                    'label' => $time,
                    'selected' => ($time === '17:00')
                ];
            }
        }

        return [
            'ready' => $automationReady,
            'webhookConfigured' => $webhookConfigured,
            'apiConfigured' => $apiConfigured,
            'defaultScheduleTime' => $defaultScheduleTime,
            'scheduleOptions' => $scheduleOptions,
            'validationMessage' => $automationReady ? '' : $validationResult['message']
        ];
    }
}
