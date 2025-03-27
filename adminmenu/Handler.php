<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;

class Handler
{
    private PluginInterface $plugin;
    private $method;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method)
    {
        $this->plugin = $plugin;
        $this->method = $method;
    }

    public function render(string $tabname, int $menuId, JTLSmarty $smarty): string
    {
        // Initialize messages array
        $messages = [];

        // Handle form submission
        if (Request::postInt('save') === 1 && Form::validateToken()) {
            $apiKey = Request::postVar('api_key', '');
            $useSandbox = Request::postInt('use_sandbox', 0);
            $ok = $this->method->savePluginSettings(array('api_key' => $apiKey, 'use_sandbox' => $useSandbox));
            if ($ok) {
                // Add success message
                $messages[] = [
                    'type' => 'success',
                    'text' => 'Settings saved successfully.'
                ];
            }
        }

        $apiKey = $this->method->getSetting('api_key');
        $useSandbox = $this->method->getSetting('use_sandbox');

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('apiKey', $apiKey);
        $smarty->assign('useSandbox', $useSandbox);
        $smarty->assign('token', Form::getTokenInput());
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/api_setup.tpl');
    }
}
