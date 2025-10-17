<?php

declare(strict_types=1);

namespace Plugin\axytos_payment;

use JTL\Widgets\AbstractWidget;
use JTL\Shop;
use Plugin\axytos_payment\helpers\Utils;
use Plugin\axytos_payment\helpers\CronHelper;
use Plugin\axytos_payment\helpers\InvoiceUpdatesHandler;

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
        
        $actionHandler = $method->createActionHandler();
        $cronHelper = new CronHelper();
        $invoiceHandler = new InvoiceUpdatesHandler($method, $db);
        
        $actionOverview = $actionHandler->getStatusOverview();
        $cronStatus = $cronHelper->getCronStatus();
        $ordersAwaitingInvoice = $invoiceHandler->getOrdersAwaitingInvoiceCount();
        
        $hasIssues = $actionOverview['broken_orders'] > 0 
                  || $cronStatus['has_stuck'] 
                  || $cronStatus['is_overdue'];
        
        $hasPending = $actionOverview['pending_orders'] > 0;
        
        $statusUrl = Shop::getAdminURL() . '/plugin/' . $plugin->getID() . '?cPluginTab=Status';
        $invoicesUrl = Shop::getAdminURL() . '/plugin/' . $plugin->getID() . '?cPluginTab=Invoices';
        
        $this->oSmarty->assign('notInstalled', false);
        $this->oSmarty->assign('pendingOrders', $actionOverview['pending_orders']);
        $this->oSmarty->assign('brokenOrders', $actionOverview['broken_orders']);
        $this->oSmarty->assign('cronStatus', $cronStatus);
        $this->oSmarty->assign('hasIssues', $hasIssues);
        $this->oSmarty->assign('hasPending', $hasPending);
        $this->oSmarty->assign('ordersAwaitingInvoice', $ordersAwaitingInvoice);
        $this->oSmarty->assign('statusUrl', $statusUrl);
        $this->oSmarty->assign('invoicesUrl', $invoicesUrl);
        
        return $this->oSmarty->fetch(
            $plugin->getPaths()->getAdminPath() . 'widget/overview.tpl'
        );
    }
}
