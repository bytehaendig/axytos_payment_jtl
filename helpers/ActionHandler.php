<?php

namespace Plugin\axytos_payment\helpers;

use JTL\DB\DbInterface;
use JTL\Checkout\Bestellung;
use JTL\Plugin\Payment\Method;
use Plugin\axytos_payment\helpers\Utils;
use Exception;

/**
 * Handles pending Axytos actions with retry logic for robustness.
 */
class ActionHandler
{
    public const MAX_RETRIES = 3;

    private Method $method;
    private ApiClient $apiClient;
    private DbInterface $db;
    private $logger;
    private ?int $axytosPaymentMethodId = null;

    /**
     * Constructor for the action handler.
     */
    public function __construct(Method $method, ApiClient $apiClient, $logger)
    {
        $this->method = $method;
        $this->apiClient = $apiClient;
        $this->db = $method->getDB();
        $this->logger = $logger;
        
        // Cache the Axytos payment method ID for efficient order checking
        $this->axytosPaymentMethodId = Utils::getPaymentMethodId($this->db);
    }

    /**
     * Check if an order uses this payment method
     */
    private function isAxytosOrder(Bestellung $order): bool
    {
        return $order->kBestellung 
            && $this->axytosPaymentMethodId 
            && $order->kZahlungsart === $this->axytosPaymentMethodId;
    }

    /**
     * Add a pending action to the order
     */
    public function addPendingAction(int $orderId, string $action, array $additionalData = []): bool
    {
        // Check if this action is already pending
        $existingAction = $this->db->selectArray(
            'axytos_actions',
            ['kBestellung', 'cAction', 'cStatus'],
            [$orderId, $action, 'pending'],
            'kAxytosAction'
        );

        if (!empty($existingAction)) {
            return true; // Action already pending
        }

        $insertData = new \stdClass();
        $insertData->kBestellung = $orderId;
        $insertData->cAction = $action;
        $insertData->cStatus = 'pending';
        $insertData->dCreatedAt = date('Y-m-d H:i:s');
        $insertData->dFailedAt = null;
        $insertData->nFailedCount = 0;
        $insertData->cFailReason = null;
        $insertData->dProcessedAt = null;
        $insertData->cData = !empty($additionalData) ? json_encode($additionalData) : null;

        $result = $this->db->insert('axytos_actions', $insertData);

        if ($result) {
            $this->logger->info('Added pending action {action} for order #{orderId}', [
                'action' => $action,
                'orderId' => $orderId
            ]);
            $this->addActionLog($orderId, $action, 'info', "Added pending $action action to processing queue");
        } else {
            throw new Exception("Failed to insert action '$action' for order #$orderId");
        }

        return $result > 0;
    }

    /**
     * Get pending actions for an order
     */
    public function getPendingActions(int $orderId): array
    {
        $actions = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung', 'cStatus'],
            [$orderId, 'pending'],
            'kAxytosAction, kBestellung, cAction, dCreatedAt, dFailedAt, nFailedCount, cFailReason, cData',
            'dCreatedAt ASC'
        );

        return $this->deserializeActionsArray($actions);
    }

    /**
     * Get completed actions for an order
     */
    public function getCompletedActions(int $orderId): array
    {
        $actions = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung', 'cStatus'],
            [$orderId, 'completed'],
            'kAxytosAction, kBestellung, cAction, dCreatedAt, dProcessedAt, cData',
            'dProcessedAt DESC'
        );

        return $this->deserializeActionsArray($actions);
    }

    /**
     * Get all actions (pending + completed) for an order
     */
    public function getAllActions(int $orderId): array
    {
        return [
            'pending' => $this->getPendingActions($orderId),
            'completed' => $this->getCompletedActions($orderId)
        ];
    }

    /**
     * Process all pending actions for an order
     */
    public function processPendingActionsForOrder(int $orderId): bool
    {
        $order = new Bestellung($orderId);
        if (!$this->isAxytosOrder($order)) {
            return false;
        }

        $pendingActions = $this->getPendingActions($orderId);
        if (empty($pendingActions)) {
            return false;
        }

        // Check if any action has exceeded retry limit - if so, skip this order
        foreach ($pendingActions as $actionData) {
            if ($this->isBroken($actionData)) {
                $this->logger->warning(
                    'Order #{orderId} has actions that exceeded max retries, skipping processing',
                    ['orderId' => $orderId],
                );
                return false;
            }
        }

        foreach ($pendingActions as $actionData) {
            $success = $this->processAction($order, $actionData);
            if (!$success) {
                // Stop processing further actions for this order
                break;
            }
        }

        return true;
    }

    /**
     * Process a single action with exception handling and status updates
     */
    private function processAction(Bestellung $order, array $actionData): bool
    {
        try {
            // Dispatch to specific action handler
            $this->dispatchAction($order, $actionData);
            
            // Mark as completed on success
            $this->markActionAsCompleted($actionData['kAxytosAction']);

            $this->logger->info('Successfully processed action {action} for order #{orderId}', [
                'action' => $actionData['cAction'],
                'orderId' => $order->kBestellung
            ]);
            // Individual action handlers already log completion with context
            
            return true;
            
        } catch (Exception $e) {
            // Record failure and check if permanently failed
            $isPermanentlyFailed = $this->recordActionFailure($actionData['kAxytosAction'], $e->getMessage());
            $failedCount = ($actionData['nFailedCount'] ?? 0) + 1;
            
            $this->logger->error('Failed to process action {action} for order #{orderId} (attempt #{attempt}): {error}', [
                'action' => $actionData['cAction'],
                'orderId' => $order->kBestellung,
                'attempt' => $failedCount,
                'error' => $e->getMessage()
            ]);
            $this->addActionLog($order->kBestellung, $actionData['cAction'], 'warning', "Action failed on attempt $failedCount: " . $e->getMessage());
            
            if ($isPermanentlyFailed) {
                $this->handleMaxRetriesExceeded($order, $actionData);
            }
            
            return false;
        }
    }

    /**
     * Dispatch to specific action handler
     */
    private function dispatchAction(Bestellung $order, array $actionData): void
    {
        switch ($actionData['cAction']) {
            case 'confirm':
                $this->processConfirmAction($order, $actionData);
                break;

            case 'shipped':
                $this->processShippedAction($order, $actionData);
                break;

            case 'invoice':
                $this->processInvoiceAction($order, $actionData);
                break;

            case 'cancel':
                $this->processCancelAction($order, $actionData);
                break;

            case 'reverse_cancel':
                $this->processReverseCancelAction($order, $actionData);
                break;

            case 'refund':
                $this->processRefundAction($order, $actionData);
                break;

            default:
                $this->logger->error('Unknown action type: {action}', ['action' => $actionData['cAction']]);
                $this->addActionLog($order->kBestellung, $actionData['cAction'], 'error', 'Unknown action type - plugin may need updating');
                throw new Exception("Unknown action type: {$actionData['cAction']}");
        }
    }

    /**
     * Process confirm action
     */
    private function processConfirmAction(Bestellung $order, array $actionData): void
    {
        $data = $actionData['data'];
        $precheckResponse = $data['orderPrecheckResponse'] ?? [];
        $transactionID = $precheckResponse['transactionMetadata']['transactionId'] ?? '';
        
        $response = $this->apiClient->orderConfirm($data);
        
        // Update order status to processing
        $upd = new \stdClass();
        $upd->cStatus = \BESTELLUNG_STATUS_IN_BEARBEITUNG;
        $this->db->update('tbestellung', 'kBestellung', (int)$order->kBestellung, $upd);
        
        $this->logger->info('Payment confirmed with transaction ID {transactionId}', ['transactionId' => $transactionID]);
        $queueTime = $actionData['dCreatedAt'] ?? '';
        $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
        $this->addActionLog($order->kBestellung, 'confirm', 'info', "Payment confirmed with Axytos (Transaction ID: $transactionID)$timingInfo");
    }

    /**
     * Process shipped action
     */
    private function processShippedAction(Bestellung $order, array $actionData): void
    {
        $data = $actionData['data'];
        $response = $this->apiClient->updateShippingStatus($data);
        $this->logger->info('Shipping status updated');
        $queueTime = $actionData['dCreatedAt'] ?? '';
        $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
        $this->addActionLog($order->kBestellung, 'shipped', 'info', "Shipping notification sent to Axytos$timingInfo");
    }

    /**
     * Process invoice action
     */
    private function processInvoiceAction(Bestellung $order, array $actionData): void
    {
        $data = $actionData['data'];
        $response = $this->apiClient->createInvoice($data);
        $responseJson = json_decode($response, true);
        $invoiceNumber = $responseJson['invoiceNumber'] ?? '';
        
        if (!empty($invoiceNumber)) {
            $this->logger->info('Invoice created with number {invoiceNumber}', ['invoiceNumber' => $invoiceNumber]);
            $queueTime = $actionData['dCreatedAt'] ?? '';
            $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
            $this->addActionLog($order->kBestellung, 'invoice', 'info', "Invoice $invoiceNumber created and submitted to Axytos$timingInfo");
        } else {
            $this->logger->info('Invoice created (no invoice number returned)');
            $queueTime = $actionData['dCreatedAt'] ?? '';
            $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
            $this->addActionLog($order->kBestellung, 'invoice', 'info', "Invoice created and submitted to Axytos$timingInfo");
        }
    }

    /**
     * Process cancel action
     */
    private function processCancelAction(Bestellung $order, array $actionData): void
    {
        $data = $actionData['data'];
        $externalOrderId = $data['externalOrderId'] ?? '';
        $response = $this->apiClient->cancelOrder($externalOrderId);
        $this->logger->info('Order cancelled (external ID: {externalOrderId})', ['externalOrderId' => $externalOrderId]);
        $queueTime = $actionData['dCreatedAt'] ?? '';
        $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
        $this->addActionLog($order->kBestellung, 'cancel', 'info', "Order cancelled with Axytos (External ID: $externalOrderId)$timingInfo");
    }

    /**
     * Process reverse cancel action
     */
    private function processReverseCancelAction(Bestellung $order, array $actionData): void
    {
        $data = $actionData['data'];
        $externalOrderId = $data['externalOrderId'] ?? '';
        $response = $this->apiClient->reverseCancelOrder($externalOrderId);
        $this->logger->info('Order reactivated (external ID: {externalOrderId})', ['externalOrderId' => $externalOrderId]);
        $queueTime = $actionData['dCreatedAt'] ?? '';
        $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
        $this->addActionLog($order->kBestellung, 'reverse_cancel', 'info', "Order reactivated with Axytos (External ID: $externalOrderId)$timingInfo");
    }

    /**
     * Process refund action
     */
    private function processRefundAction(Bestellung $order, array $actionData): void
    {
        // TODO: This would need integration with the existing payment method
        $this->logger->warning('Refund action for order {orderId} - not yet implemented', ['orderId' => $order->kBestellung]);
        $this->addActionLog($order->kBestellung, 'refund', 'warning', 'Refund functionality not yet implemented - manual processing required');
    }

    /**
     * Mark action as completed
     */
    private function markActionAsCompleted(int $actionId): bool
    {
        $updateData = new \stdClass();
        $updateData->cStatus = 'completed';
        $updateData->dProcessedAt = date('Y-m-d H:i:s');
        
        return $this->db->update(
            'axytos_actions',
            'kAxytosAction',
            $actionId,
            $updateData
        ) > 0;
    }

    /**
     * Record action failure attempt and mark as failed if max retries reached
     * @return bool true if action is now permanently failed (max retries reached)
     */
    private function recordActionFailure(int $actionId, string $failReason): bool
    {
        // Get current failed_count
        $currentAction = $this->db->select('axytos_actions', 'kAxytosAction', $actionId, null, null, null, null, false, 'nFailedCount');
        $failedCount = ($currentAction->nFailedCount ?? 0) + 1;

        $updateData = new \stdClass();
        $updateData->dFailedAt = date('Y-m-d H:i:s');
        $updateData->nFailedCount = $failedCount;
        $updateData->cFailReason = $failReason;

        $isPermanentlyFailed = $failedCount >= self::MAX_RETRIES;
        
        // If max retries reached, mark as failed
        if ($isPermanentlyFailed) {
            $updateData->cStatus = 'failed';
        }

        $this->db->update('axytos_actions', 'kAxytosAction', $actionId, $updateData);
        
        return $isPermanentlyFailed;
    }



    /**
     * Get all orders with pending actions
     */
    public function getOrdersWithPendingActions(int $limit = 50): array
    {
        $result = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions WHERE cStatus = 'pending' LIMIT $limit"
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        return $result;
    }

    /**
     * Get all orders with broken actions
     */
    public function getOrdersWithBrokenActions(int $limit = 50): array
    {
        $result = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions WHERE cStatus = 'failed' LIMIT $limit"
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        return $result;
    }

    /**
     * Get count of orders with broken actions
     */
    public function getOrdersWithBrokenActionsCount(): int
    {
        $result = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE cStatus = 'failed'"
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Process pending actions for all orders
     */
    public function processAllPendingActions(): array
    {
        $processedCount = 0;
        $failedCount = 0;
        $offset = 0;
        $batchSize = 50;

        do {
            $orderIds = $this->db->getCollection(
                "SELECT DISTINCT kBestellung FROM axytos_actions WHERE cStatus = 'pending' LIMIT $batchSize OFFSET $offset"
            )->map(static function ($row): int {
                return (int) $row->kBestellung;
            })->toArray();

            foreach ($orderIds as $orderId) {
                try {
                    // Skip orders that have actions exceeding retry limits
                    if ($this->hasActionsExceedingRetryLimit($orderId)) {
                        continue;
                    }

                    $success = $this->processPendingActionsForOrder($orderId);
                    if ($success) {
                        $processedCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    $this->logger->error('Exception during processing of pending actions for order #{orderId}: {error}', [
                        'orderId' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $offset += $batchSize;
        } while (count($orderIds) === $batchSize);

        if ($processedCount > 0 || $failedCount > 0) {
            $this->logger->info('Processed pending actions for {processed} orders, {failed} failed', [
                'processed' => $processedCount,
                'failed' => $failedCount
            ]);
        }

        return [
            'processed' => $processedCount,
            'failed' => $failedCount
        ];
    }

    /**
     * Handle when an action exceeds maximum retry limit
     */
    private function handleMaxRetriesExceeded(Bestellung $order, array $actionData): void
    {
        $orderId = $order->kBestellung;
        $action = $actionData['cAction'];

        // Log the error
        $this->logger->critical('Action {action} for order #{orderId} exceeded max retries ({maxRetries})', [
            'action' => $action,
            'orderId' => $orderId,
            'maxRetries' => self::MAX_RETRIES
        ]);
        $this->addActionLog($orderId, $action, 'error', "Action failed permanently after " . self::MAX_RETRIES . " attempts - manual intervention required");
        
        // Log critical actions to payment method log
        if (in_array($action, ['confirm', 'invoice', 'cancel', 'reverse_cancel'])) {
            $this->method->doLog("Critical action '{$action}' failed permanently for order {$orderId} after " . self::MAX_RETRIES . " retries.", \LOGLEVEL_ERROR);
        }


    }

    /**
     * Check if an action is broken (has exceeded max retry count)
     */
    public function isBroken(array $action): bool
    {
        return ($action['nFailedCount'] ?? 0) >= self::MAX_RETRIES;
    }

    /**
     * Check if order has actions that have exceeded max retry count
     */
    private function hasActionsExceedingRetryLimit(int $orderId): bool
    {
        $failedActions = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung', 'cStatus'],
            [$orderId, 'failed'],
            'COUNT(*) as count'
        );

        return !empty($failedActions) && count($failedActions) > 0;
    }

    /**
     * Remove a specific failed pending action
     */
    public function removeFailedAction(int $orderId, string $actionName): bool
    {
        $actions = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung', 'cAction', 'cStatus'],
            [$orderId, $actionName, 'failed'],
            'kAxytosAction, nFailedCount'
        );
        
        $action = !empty($actions) ? $actions[0] : null;

        if (!$action || ($action->nFailedCount ?? 0) < self::MAX_RETRIES) {
            return false;
        }

        $result = $this->db->delete('axytos_actions', (int) $action->kAxytosAction) > 0;

        if ($result) {
            $this->logger->info('Manually removed failed action {action} for order #{orderId}', [
                'action' => $actionName,
                'orderId' => $orderId
            ]);
        }

        return $result;
    }

    /**
     * Retry broken actions for a specific order
     */
    public function retryBrokenActionsForOrder(int $orderId): array
    {
        $order = new Bestellung($orderId);
        if (!$this->isAxytosOrder($order)) {
            return ['processed' => 0, 'failed' => 0, 'total_broken' => 0];
        }

        // Get failed actions
        $failedActions = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung', 'cStatus'],
            [$orderId, 'failed'],
            'kAxytosAction, cAction, cData',
            'dCreatedAt ASC'
        );

        if (empty($failedActions)) {
            return ['processed' => 0, 'failed' => 0, 'total_broken' => 0];
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($failedActions as $failedAction) {
            $this->logger->info('Retrying broken action {action} for order #{orderId}', [
                'action' => $failedAction->cAction,
                'orderId' => $orderId
            ]);

            // Reset action to pending with reset counters
            $resetData = new \stdClass();
            $resetData->cStatus = 'pending';
            $resetData->dFailedAt = null;
            $resetData->nFailedCount = 0;
            $resetData->cFailReason = null;
            
            $this->db->update(
                'axytos_actions',
                'kAxytosAction',
                (int) $failedAction->kAxytosAction,
                $resetData
            );

            $actionData = $this->deserializeActionFromDb($failedAction);
            $success = $this->processAction($order, $actionData);
            if ($success) {
                $processedCount++;
                $this->logger->info('Successfully retried broken action {action} for order #{orderId}', [
                    'action' => $failedAction->cAction,
                    'orderId' => $orderId
                ]);
            } else {
                $failedCount++;
                break; // Stop processing further actions if this one failed
            }
        }

        $totalBroken = $this->db->getSingleObject(
            "SELECT COUNT(*) as count FROM axytos_actions WHERE kBestellung = $orderId AND cStatus = 'failed'"
        );

        return [
            'processed' => $processedCount,
            'failed' => $failedCount,
            'total_broken' => (int) ($totalBroken->count ?? 0)
        ];
    }

    /**
     * Deserialize actions array from database results
     */
    private function deserializeActionsArray(array $actions): array
    {
        return array_map(function ($action) {
            return $this->deserializeActionFromDb($action);
        }, $actions);
    }

    /**
     * Add a log entry for an order action
     */
    public function addActionLog(int $orderId, string $action, string $level, string $message): bool
    {
        $insertData = new \stdClass();
        $insertData->kBestellung = $orderId;
        $insertData->cAction = $action;
        $insertData->dProcessedAt = date('Y-m-d H:i:s');
        $insertData->cLevel = $level;
        $insertData->cMessage = $message;

        $result = $this->db->insert('axytos_actionslog', $insertData);
        
        return $result > 0;
    }

    /**
     * Get action logs for an order, sorted by date
     */
    public function getActionLogs(int $orderId): array
    {
        $result = $this->db->selectAll(
            'axytos_actionslog',
            ['kBestellung'],
            [$orderId],
            'kAxytosActionsLog, cAction, dProcessedAt, cLevel, cMessage',
            'dProcessedAt ASC'
        );

        return array_map(function($log) {
            return [
                'id' => (int) $log->kAxytosActionsLog,
                'action' => $log->cAction,
                'processedAt' => $log->dProcessedAt,
                'level' => $log->cLevel,
                'message' => $log->cMessage
            ];
        }, $result);
    }

    /**
     * Deserialize single action from database result
     */
    private function deserializeActionFromDb($action): array
    {
        $data = !empty($action->cData) ? json_decode($action->cData, true) : [];
        
        return [
            'kAxytosAction' => (int) $action->kAxytosAction,
            'cAction' => $action->cAction,
            'dCreatedAt' => $action->dCreatedAt,
            'dFailedAt' => $action->dFailedAt ?? null,
            'nFailedCount' => (int) ($action->nFailedCount ?? 0),
            'cFailReason' => $action->cFailReason ?? null,
            'dProcessedAt' => $action->dProcessedAt ?? null,
            'data' => $data
        ];
    }
}
