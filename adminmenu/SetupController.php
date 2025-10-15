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
                    'text' => __('Settings saved successfully.')
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
                'text' => __('Secure webhook key generated successfully. Click "Save Settings" to store it.')
            ];
            // Pre-populate the webhook key field with generated key
            $webhookApiKey = $generatedKey;
        }

        // Handle automation script generation request
        if (Request::postInt('generate_script') === 1 && Form::validateToken()) {
            // Validate prerequisites
            $validationResult = $this->validateAutomationPrerequisites();
            if (!$validationResult['valid']) {
                $messages[] = [
                    'type' => 'error',
                    'text' => $validationResult['message']
                ];
            } else {
            $automationHandler = new AutomationHandler($this->plugin->getPaths()->getAdminPath());
            $scheduleTime = '17:00'; // Default schedule time (can be changed in config.ini)
            $pluginVersion = $this->plugin->getMeta()->getVersion();
            $webhookKey = $this->method->getSetting('webhook_api_key');
            $shopUrl = Shop::getURL();
            $automationResult = $automationHandler->generateAutomationScript($smarty, $webhookKey, $shopUrl, $scheduleTime, $pluginVersion);
        
            if ($automationResult['success']) {
                $messages[] = [
                    'type' => 'success',
                    'text' => $automationResult['message']
                ];
                // If download was requested, exit here to send file
                if ($automationResult['download']) {
                    $this->downloadAutomationScript($automationResult['zip_content'], $automationResult['filename']);
                    exit;
                }
            } else {
                $messages[] = [
                    'type' => 'error',
                    'text' => $automationResult['message']
                ];
            }
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
        // Webhook endpoint
        $webhookUrl = Shop::getURL() . '/axytos/v1/invoice-ids';

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
    echo '" . __('Webhook sent successfully') . "';
} else {
    echo '" . sprintf(__('Webhook failed with status: %s'), '\$httpCode') . "';
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
    print('" . __('Webhook sent successfully') . "')
    print('" . __('Response:') . "', response.json())
else:
    print(f'" . sprintf(__('Webhook failed with status: %s'), '{response.status_code}') . "')
    print('" . __('Response:') . "', response.text)";

        return [
            'curl' => $curlExample,
            'php' => $phpExample,
            'python' => $pythonExample
        ];
    }

    /**
     * Validate automation prerequisites with detailed messages
     */
    private function validateAutomationPrerequisites(): array
    {
        // Check if webhook key is configured
        $webhookKey = $this->method->getSetting('webhook_api_key');
        if (empty($webhookKey)) {
            return [
                'valid' => false,
                'message' => __('Webhook key must be configured before generating automation script.')
            ];
        }

        return [
            'valid' => true,
            'message' => __('Prerequisites validated successfully.')
        ];
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

        return [
            'ready' => $automationReady,
            'webhookConfigured' => $webhookConfigured,
            'apiConfigured' => $apiConfigured,
            'validationMessage' => $automationReady ? '' : $validationResult['message']
        ];
    }



    /**
     * Download automation package as ZIP file
     */
    private function downloadAutomationScript(string $content, string $filename): void
    {
        // Clear all output buffers first
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for ZIP file download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Output the content
        echo $content;
        flush();

        // Exit to prevent any further output
        exit;
    }
}
