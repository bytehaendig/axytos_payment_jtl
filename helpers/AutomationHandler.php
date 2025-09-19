<?php

namespace Plugin\axytos_payment\helpers;

use JTL\DB\DbInterface;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Exception;

class AutomationHandler
{
    private AxytosPaymentMethod $paymentMethod;
    private DbInterface $db;

    public function __construct(AxytosPaymentMethod $paymentMethod, DbInterface $db)
    {
        $this->paymentMethod = $paymentMethod;
        $this->db = $db;
    }

    /**
     * Validates that all required configuration is present for automation
     */
    public function validateConfiguration(): bool
    {
        try {
            $apiKey = $this->paymentMethod->getSetting('api_key');
            $webhookKey = $this->paymentMethod->getSetting('webhook_key');

            if (empty($apiKey)) {
                throw new Exception('API key is not configured');
            }

            if (empty($webhookKey)) {
                throw new Exception('Webhook key is not configured');
            }

            return true;
        } catch (Exception $e) {
            $this->paymentMethod->doLog("Configuration validation failed: " . $e->getMessage(), \LOGLEVEL_ERROR);
            return false;
        }
    }

    /**
     * Generates a Windows batch script with embedded configuration
     * SECURITY NOTE: Only webhook key is included - API key NEVER leaves the system
     */
    public function generateBatchScript(): string
    {
        if (!$this->validateConfiguration()) {
            throw new Exception('Configuration validation failed - cannot generate batch script');
        }

        try {
            $shopUrl = $this->getShopUrl();
            $webhookKey = $this->paymentMethod->getSetting('webhook_key');

            $template = $this->getBatchScriptTemplate();
            $variables = [
                '{{SHOP_URL}}' => $shopUrl,
                '{{WEBHOOK_KEY}}' => $webhookKey,
                '{{TIMESTAMP}}' => date('Y-m-d H:i:s')
            ];

            $script = $this->processTemplate($template, $variables);

            $this->paymentMethod->doLog("Batch script generated successfully", \LOGLEVEL_INFO);

            return $script;
        } catch (Exception $e) {
            $this->paymentMethod->doLog("Failed to generate batch script: " . $e->getMessage(), \LOGLEVEL_ERROR);
            throw new Exception('Failed to generate batch script: ' . $e->getMessage());
        }
    }

    /**
     * Gets the shop URL from configuration
     */
    private function getShopUrl(): string
    {
        // Try to get from JTL configuration first
        $shopUrl = \JTL\Shop::getURL();

        if (empty($shopUrl)) {
            // Fallback to constructing from server variables
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $shopUrl = $protocol . '://' . $host;
        }

        return rtrim($shopUrl, '/');
    }

    /**
     * Returns the batch script template (placeholder for now)
     * SECURITY: Only webhook key is embedded - API key stays server-side
     */
    private function getBatchScriptTemplate(): string
    {
        return <<<BATCH
@echo off
REM Axytos Payment Automation Script
REM Generated on: {{TIMESTAMP}}
REM Shop URL: {{SHOP_URL}}

echo Axytos Payment Automation Starting...
echo Shop URL: {{SHOP_URL}}
echo Webhook Key: [CONFIGURED]

REM TODO: Add actual automation commands here
echo Automation commands will be implemented in the next step

echo Automation completed.
pause
BATCH;
    }

    /**
     * Processes template variables
     */
    private function processTemplate(string $template, array $variables): string
    {
        $processed = $template;

        foreach ($variables as $placeholder => $value) {
            $processed = str_replace($placeholder, $value, $processed);
        }

        return $processed;
    }

    /**
     * Validates batch script content
     */
    public function validateBatchScript(string $script): bool
    {
        if (empty($script)) {
            return false;
        }

        // Check for required placeholders (excluding API key for security)
        $requiredPlaceholders = ['{{SHOP_URL}}', '{{WEBHOOK_KEY}}'];
        foreach ($requiredPlaceholders as $placeholder) {
            if (strpos($script, $placeholder) !== false) {
                $this->paymentMethod->doLog("Batch script contains unprocessed placeholder: $placeholder", \LOGLEVEL_WARNING);
                return false;
            }
        }

        // Basic validation that it's a batch file
        if (strpos($script, '@echo off') === false) {
            return false;
        }

        return true;
    }
}