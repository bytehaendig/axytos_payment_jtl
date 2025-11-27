<?php

namespace Plugin\axytos_payment\helpers;

use JTL\DB\DbInterface;
use JTL\Checkout\Bestellung;
use Plugin\axytos_payment\helpers\Utils;

/**
 * Handles invoice number changes from WaWi synchronization
 */
class InvoiceSyncHandler
{
    private $db;
    private $paymentMethod;
    private $logger;

    public function __construct($db, $paymentMethod, $logger = null)
    {
        $this->db = $db;
        $this->paymentMethod = $paymentMethod;
        $this->logger = $logger ?? $paymentMethod->getLogger();
    }

    /**
     * Called after WaWi sync to detect and react to invoice number changes
     */
    public function handleOrderUpdate($order): void
    {
        $kBestellung = (int)$order->kBestellung;
        
        // Only process Axytos orders
        if (!Utils::isAxytosOrder($this->db, $order)) {
            return;
        }

        // Get current invoice number from order attributes
        $currentAttr = $this->db->select(
            'tbestellattribut',
            ['kBestellung', 'cName'],
            [$kBestellung, 'invoice_number']
        );
        $currentInvoiceNumber = $currentAttr?->cValue ?? null;

        // Get invoice number from last invoice action (processed or pending)
        $lastInvoiceAction = $this->db->getSingleObject(
            'SELECT cData, bDone, dProcessedAt 
             FROM axytos_actions 
             WHERE kBestellung = :oid AND cAction = :action 
             ORDER BY kAxytosAction DESC 
             LIMIT 1',
            ['oid' => $kBestellung, 'action' => 'invoice']
        );

        if ($lastInvoiceAction !== null) {
            $actionData = @unserialize($lastInvoiceAction->cData);
            $actionInvoiceNumber = is_array($actionData) ? ($actionData['externalInvoiceNumber'] ?? null) : null;

            // Detect change
            if ($this->hasInvoiceNumberChanged($actionInvoiceNumber, $currentInvoiceNumber)) {
                $this->onInvoiceNumberChanged(
                    $kBestellung,
                    $order->cBestellNr,
                    $actionInvoiceNumber,
                    $currentInvoiceNumber,
                    $lastInvoiceAction->bDone ?? false
                );
            }
        } elseif ($currentInvoiceNumber !== null) {
            // No invoice action exists but WaWi added an invoice number
            $this->onInvoiceNumberAdded($kBestellung, $order->cBestellNr, $currentInvoiceNumber);
        }
    }

    /**
     * Detect if invoice number has changed
     */
    private function hasInvoiceNumberChanged(?string $old, ?string $new): bool
    {
        // Both null = no change
        if ($old === null && $new === null) {
            return false;
        }
        
        // Different values = change
        return $old !== $new;
    }

    /**
     * React to invoice number change
     */
    private function onInvoiceNumberChanged(
        int $kBestellung,
        string $orderNumber,
        ?string $oldInvoiceNumber,
        ?string $newInvoiceNumber,
        bool $wasProcessed
    ): void {
        if ($newInvoiceNumber === null) {
            // Invoice number was REMOVED by WaWi
            $this->handleInvoiceNumberRemoved($kBestellung, $orderNumber, $oldInvoiceNumber, $wasProcessed);
        } elseif ($oldInvoiceNumber === null) {
            // Invoice number was ADDED by WaWi
            $this->onInvoiceNumberAdded($kBestellung, $orderNumber, $newInvoiceNumber);
        } else {
            // Invoice number was CHANGED by WaWi
            $this->handleInvoiceNumberModified($kBestellung, $orderNumber, $oldInvoiceNumber, $newInvoiceNumber, $wasProcessed);
        }
    }

    /**
     * Handle case where WaWi removed the invoice number
     */
    private function handleInvoiceNumberRemoved(int $kBestellung, string $orderNumber, string $oldInvoiceNumber, bool $wasProcessed): void
    {
        $this->logChange($kBestellung, 'warning', 
            "Invoice number removed by WaWi (was: {$oldInvoiceNumber})" . 
            ($wasProcessed ? " - already processed" : " - was pending")
        );
    }

    /**
     * Handle case where WaWi added an invoice number
     */
    private function onInvoiceNumberAdded(int $kBestellung, string $orderNumber, string $newInvoiceNumber): void
    {
        $this->logChange($kBestellung, 'info', "Invoice number added by WaWi: {$newInvoiceNumber}");
        
        try {
            // Call invoiceWasCreated with fromWaWi=true so it doesn't set attributes
            // Process immediately since WaWi sync happens synchronously
            $this->paymentMethod->invoiceWasCreated($orderNumber, $newInvoiceNumber, true, true);
        } catch (\Exception $e) {
            $this->logChange($kBestellung, 'error', "Failed to create invoice action: " . $e->getMessage());
        }
    }

    /**
     * Handle case where WaWi changed the invoice number
     */
    private function handleInvoiceNumberModified(
        int $kBestellung,
        string $orderNumber,
        string $oldInvoiceNumber,
        string $newInvoiceNumber,
        bool $wasProcessed
    ): void {
        $this->logChange($kBestellung, 'warning', 
            "Invoice number changed by WaWi: {$oldInvoiceNumber} -> {$newInvoiceNumber}" .
            ($wasProcessed ? " (already processed - creating new action)" : " (updating pending action)")
        );

        if ($wasProcessed) {
            // Invoice already sent - create new invoice action
            // ActionHandler will handle any API errors/retries
            try {
                // Process immediately since WaWi sync happens synchronously
                $this->paymentMethod->invoiceWasCreated($orderNumber, $newInvoiceNumber, true, true);
            } catch (\Exception $e) {
                $this->logChange($kBestellung, 'error', "Failed to create new invoice action: " . $e->getMessage());
            }
        } else {
            // Invoice not yet processed - update the pending action data
            $this->updatePendingInvoiceAction($kBestellung, $newInvoiceNumber);
        }
    }

    /**
     * Update pending invoice action with new invoice number
     */
    private function updatePendingInvoiceAction(int $kBestellung, string $newInvoiceNumber): void
    {
        $pendingAction = $this->db->getSingleObject(
            'SELECT kAxytosAction, cData 
             FROM axytos_actions 
             WHERE kBestellung = :oid AND cAction = :action AND bDone = :done
             ORDER BY kAxytosAction DESC 
             LIMIT 1',
            ['oid' => $kBestellung, 'action' => 'invoice', 'done' => false]
        );

        if ($pendingAction !== null) {
            $data = @unserialize($pendingAction->cData);
            if (is_array($data) && isset($data['externalInvoiceNumber'])) {
                $data['externalInvoiceNumber'] = $newInvoiceNumber;
                
                $this->db->update(
                    'axytos_actions',
                    'kAxytosAction',
                    (int)$pendingAction->kAxytosAction,
                    (object)['cData' => serialize($data)]
                );
                
                $this->logChange($kBestellung, 'info', "Updated pending invoice action with new invoice number: {$newInvoiceNumber}");
            }
        }
    }

    /**
     * Log invoice number change using logger
     */
    private function logChange(int $kBestellung, string $level, string $message): void
    {
        $context = ['kBestellung' => $kBestellung, 'action' => 'invoice_sync'];
        $this->logger->log($level, $message, $context);
    }
}
