<?php

declare(strict_types=1);

namespace Plugin\axytos_payment;

use JTL\Widgets\AbstractWidget;
use JTL\Shop;
use Plugin\axytos_payment\helpers\Utils;
use Plugin\axytos_payment\helpers\CronHelper;

class Overview extends AbstractWidget
{
    public function init()
    {
    }

    public function getContent()
    {
        $db = Shop::Container()->getDB();
        $plugin = $this->getPlugin();
        
        $method = Utils::createPaymentMethod($db);
        if (!$method) {
            $this->oSmarty->assign('notInstalled', true);
            return $this->oSmarty->fetch(
                $plugin->getPaths()->getAdminPath() . 'widget/overview.tpl'
            );
        }
        
        // Check if API key is configured
        $apiKey = $method->getSetting('api_key');
        if (empty($apiKey)) {
            $setupUrl = Shop::getAdminURL() . '/plugin/' . $plugin->getID() . '?cPluginTab=API+Setup';
            $this->oSmarty->assign('setupRequired', true);
            $this->oSmarty->assign('setupUrl', $setupUrl);
            return $this->oSmarty->fetch(
                $plugin->getPaths()->getAdminPath() . 'widget/overview.tpl'
            );
        }
        
        // Check if sandbox mode is active
        $useSandbox = (bool)$method->getSetting('use_sandbox');
        
        $actionHandler = $method->createActionHandler();
        $cronHelper = new CronHelper();
        
        $actionOverview = $actionHandler->getStatusOverview();
        $cronStatus = $cronHelper->getCronStatus();
        
        $hasIssues = $actionOverview['broken_orders'] > 0 
                  || $cronStatus['has_stuck'] 
                  || $cronStatus['is_overdue'];
        
        $hasPending = $actionOverview['pending_orders'] > 0;
        
        $statusUrl = Shop::getAdminURL() . '/plugin/' . $plugin->getID() . '?cPluginTab=Status';
        
        $this->oSmarty->assign('setupRequired', false);
        $this->oSmarty->assign('notInstalled', false);
        $this->oSmarty->assign('useSandbox', $useSandbox);
        $this->oSmarty->assign('pendingOrders', $actionOverview['pending_orders']);
        $this->oSmarty->assign('brokenOrders', $actionOverview['broken_orders']);
        $this->oSmarty->assign('cronStatus', $cronStatus);
        $this->oSmarty->assign('hasIssues', $hasIssues);
        $this->oSmarty->assign('hasPending', $hasPending);
        $this->oSmarty->assign('statusUrl', $statusUrl);
        
        return $this->oSmarty->fetch(
            $plugin->getPaths()->getAdminPath() . 'widget/overview.tpl'
        );
    }
}
