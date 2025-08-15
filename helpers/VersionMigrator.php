<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Plugin\Payment\Method;

class VersionMigrator
{
    private $bootstrapper;

    public function __construct($bootstrapper)
    {
        $this->bootstrapper = $bootstrapper;
    }

    public function migrateVersion_0_9_3()
    {
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
        foreach ($redoIds as $orderId) {
            error_log("migrateVersion_0_9_3:: re-cancel order $orderId");
            $method->doCancelOrder($orderId);
            $upd                = new \stdClass();
            $upd->cStatus       = \BESTELLUNG_STATUS_STORNO;
            $db->update('tbestellung', 'kBestellung', $orderId, $upd);
        }
    }
}
