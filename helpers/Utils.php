<?php

namespace Plugin\axytos_payment\helpers;

use JTL\DB\DbInterface;
use JTL\Plugin\Payment\Method;
use JTL\Checkout\Bestellung;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;

class Utils
{
    public const PLUGIN_ID = 'axytos_payment';

    /**
     * Get the module ID prefix for this plugin
     * 
     * @param DbInterface $db
     * @return string|null Returns 'kPlugin_{id}_' or null if plugin not found
     */
    public static function getModuleIdPrefix(DbInterface $db): ?string
    {
        $plugin = $db->select('tplugin', 'cPluginID', self::PLUGIN_ID);
        
        if (!$plugin || !$plugin->kPlugin) {
            return null;
        }
        
        return 'kPlugin_' . $plugin->kPlugin . '_';
    }

    /**
     * Get the full module ID for this plugin's payment method
     * 
     * @param DbInterface $db
     * @return string|null Returns full module ID or null if not found
     */
    public static function getModuleId(DbInterface $db): ?string
    {
        $prefix = self::getModuleIdPrefix($db);
        if (!$prefix) {
            return null;
        }
        
        $result = $db->getCollection(
            "SELECT cModulId FROM tzahlungsart WHERE cModulId LIKE '" . $prefix . "%'"
        );
        
        if ($result->isEmpty()) {
            return null;
        }
        
        return $result->first()->cModulId;
    }

    /**
     * Get the payment method ID (kZahlungsart) for this plugin
     * 
     * @param DbInterface $db
     * @return int|null Returns kZahlungsart or null if not found
     */
    public static function getPaymentMethodId(DbInterface $db): ?int
    {
        $moduleId = self::getModuleId($db);
        if (!$moduleId) {
            return null;
        }
        
        $result = $db->select('tzahlungsart', 'cModulId', $moduleId);
        return $result ? (int)$result->kZahlungsart : null;
    }

    /**
     * Check if an order uses the Axytos payment method
     * 
     * @param DbInterface $db
     * @param Bestellung $order
     * @return bool
     */
    public static function isAxytosOrder(DbInterface $db, Bestellung $order): bool
    {
        if (!$order->kBestellung || !$order->kZahlungsart) {
            return false;
        }
        
        $axytosPaymentMethodId = self::getPaymentMethodId($db);
        return $axytosPaymentMethodId && $order->kZahlungsart === $axytosPaymentMethodId;
    }

    /**
     * Create and return the Axytos payment method instance
     * 
     * @param DbInterface $db
     * @return AxytosPaymentMethod|null Returns payment method instance or null if not found
     */
    public static function createPaymentMethod(DbInterface $db): ?AxytosPaymentMethod
    {
        $moduleId = self::getModuleId($db);
        if (!$moduleId) {
            return null;
        }
        
        $method = Method::create($moduleId);
        
        return ($method instanceof AxytosPaymentMethod) ? $method : null;
    }
}