<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Smarty\JTLSmarty;
use Exception;
use ZipArchive;

class AutomationHandler
{
    private string $adminPath;

    public function __construct(string $adminPath)
    {
        $this->adminPath = $adminPath;
    }

    /**
     * Generate automation ZIP package
     */
    public function generateAutomationScript(JTLSmarty $smarty, string $webhookKey, string $shopUrl, string $scheduleTime = '09:00', string $pluginVersion = ''): array
    {
        try {
            // Prepare template variables
            $templateVars = [
                'SHOP_URL' => $shopUrl,
                'WEBHOOK_KEY_RAW' => $webhookKey,  // Raw key for use in scripts
                'SCHEDULE_TIME' => $scheduleTime,
                'TIMESTAMP' => date('Y-m-d H:i:s'),
                'PLUGIN_VERSION' => $pluginVersion
            ];

            // Assign all template variables
            foreach ($templateVars as $key => $value) {
                $smarty->assign($key, $value);
            }

            // Generate all script files from templates
            $files = [
                'config.ini' => $smarty->fetch($this->adminPath . 'template/automation/config.ini.tpl'),
                'README.txt' => $smarty->fetch($this->adminPath . 'template/automation/README.txt.tpl'),
                'install.bat' => $smarty->fetch($this->adminPath . 'template/automation/installer.bat.tpl'),
                'axytos_export.ps1' => $smarty->fetch($this->adminPath . 'template/automation/export-script.ps1.tpl'),
                'automation_runner.bat' => $smarty->fetch($this->adminPath . 'template/automation/runner.bat.tpl'),
                'uninstall.bat' => $smarty->fetch($this->adminPath . 'template/automation/uninstall.bat.tpl')
            ];

            // Create ZIP file
            $zipResult = $this->createZipArchive($files, $pluginVersion);

            if (!$zipResult['success']) {
                throw new Exception($zipResult['message']);
            }

            return [
                'success' => true,
                'message' => 'Automation package generated successfully. Extract the ZIP file and run install.bat to set up the automation system.',
                'zip_content' => $zipResult['content'],
                'filename' => $zipResult['filename'],
                'download' => true
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate automation package: ' . $e->getMessage(),
                'download' => false
            ];
        }
    }

    /**
     * Create ZIP archive containing all automation files
     */
    private function createZipArchive(array $files, string $version): array
    {
        // Create temporary file for ZIP
        $tempZipPath = sys_get_temp_dir() . '/axytos_automation_' . uniqid() . '.zip';

        // Create ZIP archive
        $zip = new ZipArchive();
        $result = $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            return [
                'success' => false,
                'message' => 'Failed to create ZIP archive'
            ];
        }

        // Add all files to ZIP
        foreach ($files as $filename => $content) {
            if (!$zip->addFromString($filename, $content)) {
                $zip->close();
                @unlink($tempZipPath);
                return [
                    'success' => false,
                    'message' => "Failed to add file to ZIP: $filename"
                ];
            }
        }

        // Close ZIP archive
        $zip->close();

        // Read ZIP content
        $zipContent = file_get_contents($tempZipPath);

        // Clean up temporary file
        @unlink($tempZipPath);

        // Generate final filename
        $filename = 'axytos_automation_v' . str_replace('.', '_', $version) . '_' . date('Y-m-d') . '.zip';

        return [
            'success' => true,
            'content' => $zipContent,
            'filename' => $filename
        ];
    }
}
