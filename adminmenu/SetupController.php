<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Shop;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;

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

        // Get webhook configuration data
        $webhookConfig = $this->getWebhookConfiguration();

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('apiKey', $apiKey);
        $smarty->assign('webhookApiKey', $webhookApiKey);
        $smarty->assign('useSandbox', $useSandbox);
        $smarty->assign('webhookConfig', $webhookConfig);
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
}
