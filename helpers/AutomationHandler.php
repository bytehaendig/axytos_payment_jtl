<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Smarty\JTLSmarty;
use Exception;

class AutomationHandler
{
    private string $adminPath;

    public function __construct(string $adminPath)
    {
        $this->adminPath = $adminPath;
    }

    /**
     * Generate automation script
     */
    public function generateAutomationScript(JTLSmarty $smarty, string $webhookKey, string $shopUrl, string $scheduleTime = '09:00', string $pluginVersion = ''): array
    {
        try {

            // Prepare template variables
            $templateVars = [
                'SHOP_URL' => $shopUrl,
                'WEBHOOK_KEY' => $webhookKey,
                'SCHEDULE_TIME' => $scheduleTime,
                'TIMESTAMP' => date('Y-m-d H:i:s'),
                'PLUGIN_VERSION' => $pluginVersion
            ];

            // Generate individual script components
            foreach ($templateVars as $key => $value) {
                $smarty->assign($key, $value);
            }
            
            $powershellScript = $smarty->fetch($this->adminPath . 'template/automation/export-script.ps1.tpl');
            $uninstallScript = $smarty->fetch($this->adminPath . 'template/automation/uninstall.bat.tpl');
            $runnerScript = $smarty->fetch($this->adminPath . 'template/automation/runner.bat.tpl');

            // Add the script contents as template variables for the installer
            $templateVars['POWERSHELL_SCRIPT_CONTENT'] = $powershellScript;
            $templateVars['UNINSTALL_SCRIPT_CONTENT'] = $uninstallScript;
            $templateVars['RUNNER_SCRIPT_CONTENT'] = $runnerScript;

            // Generate final installer with embedded scripts
            foreach ($templateVars as $key => $value) {
                $smarty->assign($key, $value);
            }
            $finalScript = $smarty->fetch($this->adminPath . 'template/automation/installer.bat.tpl');

            // Generate filename
            $filename = 'axytos_automation_' . date('Y-m-d_H-i-s') . '.bat';

            return [
                'success' => true,
                'message' => 'Automation script generated successfully. After installation, the uninstall script will be available locally for removing the automation system.',
                'script_content' => $finalScript,
                'filename' => $filename,
                'download' => true
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate automation script: ' . $e->getMessage(),
                'download' => false
            ];
        }
    }










}