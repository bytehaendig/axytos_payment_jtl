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

    /**
     * Load order by order number (cBestellNr)
     *
     * This method loads an order from the database using its order number and verifies
     * that it uses the Axytos payment method. This ensures that only Axytos orders
     * are processed by the payment integration.
     *
     * @param DbInterface $db Database connection instance
     * @param string $orderNumber The order number (cBestellNr) to search for
     * @return Bestellung|null Returns the order instance if found and is an Axytos order, null if order doesn't exist
     * @throws \Exception If order exists in database but does not use Axytos payment method
     *
     * @example
     * ```php
     * $order = Utils::loadOrderByOrderNumber($db, 'ORDER123');
     * if ($order) {
     *     // Order exists and is guaranteed to be an Axytos order
     *     $this->processAxytosOrder($order);
     * }
     * ```
     */
    public static function loadOrderByOrderNumber(DbInterface $db, string $orderNumber): ?Bestellung
    {
        // Try to find order by order number
        $result = $db->select('tbestellung', 'cBestellNr', $orderNumber);
        if ($result) {
            $order = new Bestellung((int)$result->kBestellung);
            $order->fuelleBestellung(false);

            // Verify that this is an Axytos order
            if (!self::isAxytosOrder($db, $order)) {
                throw new \Exception("Order '{$orderNumber}' exists but is not an Axytos order");
            }

            return $order;
        }

        return null;
    }
}