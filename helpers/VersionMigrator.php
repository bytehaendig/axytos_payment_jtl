<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Checkout\Bestellung;
use JTL\Plugin\Payment\Method;

class VersionMigrator
{
    private $bootstrapper;

    public function __construct($bootstrapper)
    {
        $this->bootstrapper = $bootstrapper;
    }

    public function rerun_failed_cancellations()
    {
        // TODO: since this may affect a bigger amount of orders,
        // running this sinchronously (as is done here) will exceed the PHP request limits
        // so we need to run this in cron jobs or something like that
        $db = $this->bootstrapper->getDB();
        $moduleID = $this->bootstrapper->getModuleID();
        $cancelIds = [];
        $cancelQuery = "SELECT * from tzahlungslog where cModulId = '$moduleID' AND nLevel = 1 AND cLog LIKE 'Order cancellation failed for order %'";
        error_log("migrateVersion_0_9_3:: run $cancelQuery");
        $cancelEntries = $db->getObjects($cancelQuery);
        $reactIds = [];
        $reactQuery = "SELECT * from tzahlungslog where cModulId = '$moduleID' AND nLevel = 1 AND cLog LIKE 'Order reactivation failed for order %'";
        $reactEntries = $db->getObjects($reactQuery);
        foreach ($cancelEntries as $entry) {
            preg_match('/order (\d+):/', $entry->cLog, $matches);
            $orderId = $matches[1];
            array_push($cancelIds, $orderId);
        }
        foreach ($reactEntries as $entry) {
            preg_match('/order (\d+):/', $entry->cLog, $matches);
            $orderId = $matches[1];
            array_push($reactIds, $orderId);
        }
        $redoIds = array_diff($cancelIds, $reactIds);
        $method = Method::create($moduleID);
        $db = $this->bootstrapper->getDB();
        $actionHandler = $method->createActionHandler();
        foreach ($redoIds as $orderId) {
            error_log("migrateVersion_0_9_3:: re-cancel order $orderId");
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            $actionHandler->addPendingAction($orderId, 'cancel', ['externalOrderId' => $order->cBestellNr]);
            
            // Set order status to cancelled like the original code
            $upd = new \stdClass();
            $upd->cStatus = \BESTELLUNG_STATUS_STORNO;
            $db->update('tbestellung', 'kBestellung', $orderId, $upd);
        }
    }
}
