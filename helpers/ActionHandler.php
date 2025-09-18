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

    /**
     * Constructor for the action handler.
     */
    public function __construct(Method $method, ApiClient $apiClient, $logger)
    {
        $this->method = $method;
        $this->apiClient = $apiClient;
        $this->db = $method->getDB();
        $this->logger = $logger;
    }

    /**
     * Add a pending action to the order
     */
    public function addPendingAction(int $orderId, string $action, array $additionalData = []): bool
    {
        // Check if this action is already pending
        $existingAction = $this->db->selectArray(
            'axytos_actions',
            ['kBestellung', 'cAction', 'bDone'],
            [$orderId, $action, false],
            '*'
        );

        if (!empty($existingAction)) {
            return true; // Action already pending
        }

        $insertData = new \stdClass();
        $insertData->kBestellung = $orderId;
        $insertData->cAction = $action;
        $insertData->bDone = false;
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
            ['kBestellung', 'bDone'],
            [$orderId, false],
            '*',
            'dCreatedAt DESC'
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
            ['kBestellung', 'bDone'],
            [$orderId, true],
            '*',
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
     * Process pending actions for an order
     */
    public function processPendingActionsForOrder(int $orderId): bool
    {
        $order = new Bestellung($orderId);
        $pendingActions = $this->getPendingActions($orderId);
        if (empty($pendingActions)) {
            return false;
        }

        $processedAny = false;
        foreach ($pendingActions as $actionData) {
            if ($this->getStatus($actionData) === 'broken') {
                $this->logger->debug(
                    'Skipping broken action {action} for order #{orderId} (exceeded max retries)',
                    ['action' => $actionData['cAction'], 'orderId' => $orderId]
                );
                // don't process further actions
                break;
            }

            $success = $this->processAction($order, $actionData);
            if ($success) {
                $processedAny = true;
            } else {
                // Stop processing further actions for this order if one fails
                break;
            }
        }

        return $processedAny;
    }

    /**
     * Process a single action with exception handling and status updates
     */
    private function processAction(Bestellung $order, array $actionData): bool
    {
        try {
            $this->dispatchAction($order, $actionData);
            $this->recordActionSuccess($actionData);
            return true;
        } catch (Exception $e) {
            $this->recordActionFailure($actionData, $e->getMessage());
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
        try {
            $response = $this->apiClient->orderConfirm($data);
        } catch (Exception $ex) {
            $this->method->doLog("Order confirmation failed for order {$order->kBestellung}.", \LOGLEVEL_ERROR);
            throw $ex;
        }
        // Update order status to processing
        $upd = new \stdClass();
        $upd->cStatus = \BESTELLUNG_STATUS_IN_BEARBEITUNG;
        $this->db->update('tbestellung', 'kBestellung', (int)$order->kBestellung, $upd);
        
        $this->logger->info('Payment confirmed with transaction ID {transactionId}', ['transactionId' => $transactionID]);
        $queueTime = $actionData['dCreatedAt'] ?? '';
        $timingInfo = $queueTime ? " (queued: $queueTime)" : '';
        $this->addActionLog($order->kBestellung, 'confirm', 'info', "Payment confirmed with Axytos (Transaction ID: $transactionID)$timingInfo");
        $this->method->doLog("Payment for order {$order->kBestellung} accepted with transaction ID {$transactionID}.", \LOGLEVEL_NOTICE);
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
    private function recordActionSuccess(array $action): bool
    {
        $updateData = new \stdClass();
        $updateData->bDone = true;
        $updateData->dProcessedAt = date('Y-m-d H:i:s');
        $result = $this->db->update(
            'axytos_actions',
            'kAxytosAction',
            $action['kAxytosAction'],
            $updateData
        );
        $this->logger->info('Successfully processed action {action} for order #{orderId}', [
            'action' => $action['cAction'],
            'orderId' => $action['kBestellung']
        ]);
        return $result > 0;
    }

    /**
     * Record action failure attempt and mark as failed if max retries reached
     * @return bool true if action is now permanently failed (max retries reached)
     */
    private function recordActionFailure(array $action, string $failReason): bool
    {
        // Get current failed_count
        $failedCount = ($action['nFailedCount'] ?? 0) + 1;

        $updateData = new \stdClass();
        $updateData->dFailedAt = date('Y-m-d H:i:s');
        $updateData->nFailedCount = $failedCount;
        $updateData->cFailReason = $failReason;

        $isPermanentlyFailed = $failedCount > self::MAX_RETRIES;
        $this->db->update('axytos_actions', 'kAxytosAction', $action['kAxytosAction'], $updateData);
        $this->logger->warning('Failed to process action {action} for order {orderId} (attempt #{attempt}): {error}', [
            'action' => $action['cAction'],
            'orderId' => $action['kBestellung'],
            'attempt' => $failedCount,
            'error' => $failReason,
        ]);
        $this->addActionLog($action['kBestellung'], $action['cAction'], 'warning', "Action failed on attempt $failedCount: " . Utils::sanitizeErrorMessage($failReason));
        if ($isPermanentlyFailed) {
            $this->method->doLog("Action {$action['cAction']} for order {$action['kBestellung']} failed {$failedCount} - manual interaction required");
            $this->logger->error('Action {action} for order #{orderId} exceeded max retries ({maxRetries}) - manual interaction requried', [
                'action' => $action['cAction'],
                'orderId' => $action['kBestellung'],
                'maxRetries' => self::MAX_RETRIES
            ]);
        }
        return $isPermanentlyFailed;
    }



    /**
     * Get all orders with pending actions
     */
    public function getOrdersWithPendingActions(int $limit = 50): array
    {
        $result = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions WHERE bDone = FALSE AND nFailedCount <= :maxRetries LIMIT :limit",
            ['maxRetries' => self::MAX_RETRIES, 'limit' => $limit]
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
            "SELECT DISTINCT kBestellung FROM axytos_actions WHERE bDone = FALSE AND nFailedCount > :maxRetries LIMIT :limit",
            ['maxRetries' => self::MAX_RETRIES, 'limit' => $limit]
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
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE bDone = FALSE AND nFailedCount > :maxRetries",
            ['maxRetries' => self::MAX_RETRIES]
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
                "SELECT DISTINCT kBestellung FROM axytos_actions WHERE bDone = FALSE AND nFailedCount <= :maxRetries LIMIT :batchSize OFFSET :offset",
                ['maxRetries' => self::MAX_RETRIES, 'batchSize' => $batchSize, 'offset' => $offset]
            )->map(static function ($row): int {
                return (int) $row->kBestellung;
            })->toArray();

            foreach ($orderIds as $orderId) {
                try {
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
     * Get the status of an action based on bDone and nFailedCount
     * 
     * @param array $action Action data with bDone and nFailedCount fields
     * @return string 'completed', 'pending', or 'broken'
     */
    public function getStatus(array $action): string
    {
        if ($action['bDone'] ?? false) {
            return 'completed';
        }
        
        $failedCount = (int) ($action['nFailedCount'] ?? 0);
        if ($failedCount > self::MAX_RETRIES) {
            return 'broken';
        }
        
        return 'pending';
    }



    /**
     * Remove a specific broken action
     */
    public function removeBrokenAction(int $orderId, string $actionName): bool
    {
        // Find actions that are broken: not done AND exceeded max retries
        $actions = $this->db->getCollection(
            "SELECT * FROM axytos_actions WHERE kBestellung = :orderId AND cAction = :actionName AND bDone = FALSE AND nFailedCount > :maxRetries",
            ['orderId' => $orderId, 'actionName' => $actionName, 'maxRetries' => self::MAX_RETRIES]
        )->toArray();
        
        $action = !empty($actions) ? $actions[0] : null;

        if (!$action) {
            return false;
        }

        $result = $this->db->delete('axytos_actions', 'kAxytosAction', (int) $action->kAxytosAction) > 0;

        if ($result) {
            $this->logger->info('Manually removed broken action {action} for order #{orderId}', [
                'action' => $actionName,
                'orderId' => $orderId
            ]);
            $this->addActionLog($orderId, $actionName, 'info', "Broken action manually removed from admin interface");
        }

        return $result;
    }

    /**
     * Retry broken actions for a specific order
     */
    public function retryBrokenActionsForOrder(int $orderId): array
    {
        $order = new Bestellung($orderId);
        // Get broken actions
        // Find broken actions: not done AND exceeded max retries
        $brokenActions = $this->db->getCollection(
            "SELECT * FROM axytos_actions WHERE kBestellung = :orderId AND bDone = FALSE AND nFailedCount > :maxRetries ORDER BY dCreatedAt DESC",
            ['orderId' => $orderId, 'maxRetries' => self::MAX_RETRIES]
        )->toArray();

        if (empty($brokenActions)) {
            return ['processed' => 0, 'failed' => 0, 'total_broken' => 0];
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($brokenActions as $brokenAction) {
            $this->logger->info('Retrying broken action {action} for order #{orderId}', [
                'action' => $brokenAction->cAction,
                'orderId' => $orderId
            ]);

            // Reset action to pending with reset counters
            $resetData = new \stdClass();
            $resetData->bDone = false;
            $resetData->dFailedAt = null;
            $resetData->nFailedCount = 0;
            $resetData->cFailReason = null;
            
            $this->db->update(
                'axytos_actions',
                'kAxytosAction',
                (int) $brokenAction->kAxytosAction,
                $resetData
            );

            $this->addActionLog($orderId, $brokenAction->cAction, 'info', "Broken action manually retried from admin interface");

            $actionData = $this->deserializeActionFromDb($brokenAction);
            $success = $this->processAction($order, $actionData);
            if ($success) {
                $processedCount++;
                $this->logger->info('Successfully retried broken action {action} for order #{orderId}', [
                    'action' => $brokenAction->cAction,
                    'orderId' => $orderId
                ]);
            } else {
                $failedCount++;
                break; // Stop processing further actions if this one failed
            }
        }
        return [
            'processed' => $processedCount,
            'failed' => $failedCount,
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
            'dProcessedAt DESC, kAxytosActionsLog DESC'
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
     * Get both pending and broken actions for an order (for search results)
     */
    public function getPendingAndBrokenActions(int $orderId): array
    {
        $actions = $this->db->getCollection(
            "SELECT * FROM axytos_actions
             WHERE kBestellung = :orderId AND bDone = FALSE
             ORDER BY dCreatedAt DESC",
            ['orderId' => $orderId]
        )->toArray();

        return $this->deserializeActionsArray($actions);
    }

    /**
     * Get compact timeline showing actions with expandable logs
     */
    public function getCompactTimeline(int $orderId): array
    {
        // Get all actions for this order
        $actions = $this->db->getCollection(
            "SELECT * FROM axytos_actions WHERE kBestellung = :orderId ORDER BY dCreatedAt DESC",
            ['orderId' => $orderId]
        )->toArray();

        // Get all action logs grouped by action
        $logs = $this->db->getCollection(
            "SELECT * FROM axytos_actionslog WHERE kBestellung = :orderId ORDER BY dProcessedAt DESC, kAxytosActionsLog DESC",
            ['orderId' => $orderId]
        )->toArray();

        // Group logs by action type
        $logsByAction = [];
        foreach ($logs as $log) {
            $logsByAction[$log->cAction][] = [
                'id' => (int) $log->kAxytosActionsLog,
                'timestamp' => $log->dProcessedAt,
                'level' => $log->cLevel,
                'message' => $log->cMessage
            ];
        }

        // Create compact timeline entries
        $timeline = [];
        foreach ($actions as $action) {
            $status = $this->getActionStatus($action);
            $actionLogs = $logsByAction[$action->cAction] ?? [];
            
            // Determine the most recent timestamp for sorting
            $latestTimestamp = $action->dCreatedAt;
            if ($action->dProcessedAt) {
                $latestTimestamp = max($latestTimestamp, $action->dProcessedAt);
            }
            if ($action->dFailedAt) {
                $latestTimestamp = max($latestTimestamp, $action->dFailedAt);
            }
            if (!empty($actionLogs)) {
                $latestLogTime = max(array_column($actionLogs, 'timestamp'));
                $latestTimestamp = max($latestTimestamp, $latestLogTime);
            }

            $timeline[] = [
                'action' => $action->cAction,
                'status' => $status,
                'level' => $this->getStatusLevel($status),
                'summary' => $this->getActionSummary($action, $status),
                'created_at' => $action->dCreatedAt,
                'completed_at' => $action->dProcessedAt,
                'failed_at' => $action->dFailedAt,
                'failed_count' => (int) $action->nFailedCount,
                'fail_reason' => $action->cFailReason ?? '',
                'latest_timestamp' => $latestTimestamp,
                'logs' => $actionLogs,
                'log_count' => count($actionLogs),
                'details' => [
                    'action_id' => (int) $action->kAxytosAction
                ]
            ];
        }

        // Sort by latest activity timestamp (newest first)
        usort($timeline, function($a, $b) {
            return strcmp($b['latest_timestamp'], $a['latest_timestamp']);
        });

        return $timeline;
    }



    /**
     * Get action status for timeline purposes
     */
    private function getActionStatus($action): string
    {
        if ($action->bDone) {
            return 'completed';
        }
        
        if ($action->nFailedCount > self::MAX_RETRIES) {
            return 'broken';
        }
        
        if ($action->nFailedCount > 0) {
            return 'retryable';
        }
        
        return 'pending';
    }

    /**
     * Get human-readable message for action creation
     */
    private function getActionCreatedMessage(string $action): string
    {
        $messages = [
            'precheck' => 'Precheck action queued',
            'confirm' => 'Order confirmation action queued',
            'invoice' => 'Invoice creation action queued',
            'shipped' => 'Shipping notification action queued',
            'cancel' => 'Order cancellation action queued',
            'reverse_cancel' => 'Order reactivation action queued',
            'refund' => 'Refund action queued'
        ];
        
        return $messages[$action] ?? ucfirst($action) . ' action queued';
    }

    /**
     * Get human-readable message for action completion
     */
    private function getActionCompletedMessage(string $action): string
    {
        $messages = [
            'precheck' => 'Precheck completed successfully',
            'confirm' => 'Order confirmation completed successfully',
            'invoice' => 'Invoice creation completed successfully',
            'shipped' => 'Shipping notification completed successfully',
            'cancel' => 'Order cancellation completed successfully',
            'reverse_cancel' => 'Order reactivation completed successfully',
            'refund' => 'Refund completed successfully'
        ];
        
        return $messages[$action] ?? ucfirst($action) . ' completed successfully';
    }

    /**
     * Get human-readable message for action failure
     */
    private function getActionFailedMessage(string $action, int $failedCount): string
    {
        $actionNames = [
            'precheck' => 'Precheck',
            'confirm' => 'Order confirmation',
            'invoice' => 'Invoice creation',
            'shipped' => 'Shipping notification',
            'cancel' => 'Order cancellation',
            'reverse_cancel' => 'Order reactivation',
            'refund' => 'Refund'
        ];
        
        $actionName = $actionNames[$action] ?? ucfirst($action);
        
        if ($failedCount > self::MAX_RETRIES) {
            return $actionName . ' failed permanently after ' . $failedCount . ' attempts - manual intervention required';
        } else {
            return $actionName . ' failed on attempt ' . $failedCount . ' - will retry automatically';
        }
    }

    /**
     * Get status level for compact timeline
     */
    private function getStatusLevel(string $status): string
    {
        switch ($status) {
            case 'completed':
                return 'success';
            case 'broken':
                return 'error';
            case 'retryable':
                return 'warning';
            case 'pending':
            default:
                return 'info';
        }
    }

    /**
     * Get action summary message for compact timeline
     */
    private function getActionSummary($action, string $status): string
    {
        $actionNames = [
            'precheck' => 'Precheck',
            'confirm' => 'Order Confirmation',
            'invoice' => 'Invoice Creation',
            'shipped' => 'Shipping Notification',
            'cancel' => 'Order Cancellation',
            'reverse_cancel' => 'Order Reactivation',
            'refund' => 'Refund Processing'
        ];
        
        $actionName = $actionNames[$action->cAction] ?? ucfirst($action->cAction);
        
        switch ($status) {
            case 'completed':
                return $actionName . ' completed successfully';
            case 'broken':
                return $actionName . ' failed permanently (' . $action->nFailedCount . ' attempts)';
            case 'retryable':
                return $actionName . ' failed, will retry (' . $action->nFailedCount . '/' . self::MAX_RETRIES . ' attempts)';
            case 'pending':
            default:
                return $actionName . ' queued for processing';
        }
    }

    /**
     * Get status overview counts
     */
    public function getStatusOverview(): array
    {
        // Get counts for different action states
        $pendingCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE bDone = FALSE AND nFailedCount <= :maxRetries",
            ['maxRetries' => self::MAX_RETRIES]
        );
        
        $brokenCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE bDone = FALSE AND nFailedCount > :maxRetries",
            ['maxRetries' => self::MAX_RETRIES]
        );
        
        $totalOrdersCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions"
        );

        return [
            'pending_orders' => (int) ($pendingCount->count ?? 0),
            'broken_orders' => (int) ($brokenCount->count ?? 0),
            'total_orders' => (int) ($totalOrdersCount->count ?? 0)
        ];
    }

    /**
     * Get orders with any actions (pending or broken) for unified table
     */
    public function getOrdersWithActions(int $limit = 50): array
    {
        $orderIds = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions
             WHERE bDone = FALSE
             ORDER BY dCreatedAt DESC LIMIT :limit",
            ['limit' => $limit]
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        return $orderIds;
    }

    /**
     * Get detailed actions breakdown for unified actions table
     */
    public function getOrderActionsBreakdown(int $orderId): array
    {
        // Get all actions for this order in a single query
        $allActionsData = $this->db->selectAll(
            'axytos_actions',
            ['kBestellung'],
            [$orderId],
            '*',
            'dCreatedAt DESC'
        );

        // Separate actions by their actual status
        $pendingActions = [];
        $retryableActions = [];
        $brokenActions = [];
        $completedActions = [];

        foreach ($allActionsData as $actionData) {
            $action = [
                'cAction' => $actionData->cAction,
                'bDone' => $actionData->bDone,
                'dCreatedAt' => $actionData->dCreatedAt,
                'dProcessedAt' => $actionData->dProcessedAt,
                'dFailedAt' => $actionData->dFailedAt,
                'nFailedCount' => $actionData->nFailedCount,
                'cFailReason' => $actionData->cFailReason
            ];

            $actionStatus = $this->getStatus($action);
            if ($actionStatus === 'completed') {
                $completedActions[] = [
                    'action' => $action['cAction'],
                    'created_at' => $action['dCreatedAt'],
                    'completed_at' => $action['dProcessedAt'],
                    'status' => 'completed',
                    'status_text' => 'completed',
                    'status_color' => '#198754', // green
                    'fail_reason' => null
                ];
            } elseif ($actionStatus === 'broken') {
                $brokenActions[] = [
                    'action' => $action['cAction'],
                    'created_at' => $action['dCreatedAt'],
                    'failed_at' => $action['dFailedAt'],
                    'failed_count' => $action['nFailedCount'],
                    'status' => 'broken',
                    'status_text' => $action['nFailedCount'] > 0 ? sprintf('failed %dx, broken', $action['nFailedCount']) : 'broken',
                    'status_color' => '#dc3545', // red
                    'fail_reason' => $action['cFailReason'] ?? null
                ];
            } elseif ($actionStatus === 'pending') {
                if (empty($action['dFailedAt'])) {
                    // True pending actions (never failed)
                    $pendingActions[] = [
                        'action' => $action['cAction'],
                        'created_at' => $action['dCreatedAt'],
                        'status' => 'pending',
                        'status_text' => 'pending',
                        'status_color' => '#fd7e14', // orange
                        'fail_reason' => null
                    ];
                } else {
                    // Failed pending actions - check if broken or retryable
                    $failedCount = (int) ($action['nFailedCount'] ?? 0);
                    if ($failedCount > self::MAX_RETRIES) {
                        $brokenActions[] = [
                            'action' => $action['cAction'],
                            'created_at' => $action['dCreatedAt'],
                            'failed_at' => $action['dFailedAt'],
                            'failed_count' => $failedCount,
                            'status' => 'broken',
                            'status_text' => $failedCount > 0 ? sprintf('failed %dx, broken', $failedCount) : 'broken',
                            'status_color' => '#dc3545', // red
                            'fail_reason' => $action['cFailReason'] ?? null
                        ];
                    } else {
                        $retryableActions[] = [
                            'action' => $action['cAction'],
                            'created_at' => $action['dCreatedAt'],
                            'failed_at' => $action['dFailedAt'],
                            'failed_count' => $failedCount,
                            'status' => 'retryable',
                            'status_text' => $failedCount > 0 ? sprintf('failed %dx, will retry', $failedCount) : 'will retry',
                            'status_color' => '#ffc107', // yellow/orange
                            'fail_reason' => $action['cFailReason'] ?? null
                        ];
                    }
                }
            }
        }

        return [
            'pending_actions' => $pendingActions,
            'retryable_actions' => $retryableActions,
            'broken_actions' => $brokenActions,
            'completed_actions' => $completedActions,
            'has_pending' => !empty($pendingActions),
            'has_retryable' => !empty($retryableActions),
            'has_broken' => !empty($brokenActions),
            'has_completed' => !empty($completedActions),
            'total_actions' => count($pendingActions) + count($retryableActions) + count($brokenActions) + count($completedActions),
            // Add fields to match recentOrders format for consistent template
            'pending_count' => count($pendingActions) + count($retryableActions), // Pending + retry actions
            'completed_count' => count($completedActions)
        ];
    }

    /**
     * Check if an order has broken actions
     */
    public function hasOrderBrokenActions(int $orderId): bool
    {
        $brokenAction = $this->db->getSingleObject(
            "SELECT * FROM axytos_actions WHERE kBestellung = :orderId AND bDone = FALSE AND nFailedCount > :maxRetries LIMIT 1",
            ['orderId' => $orderId, 'maxRetries' => self::MAX_RETRIES]
        );
        
        return $brokenAction !== null;
    }

    /**
     * Get recent orders with basic action counts (fallback when no pending/broken actions exist)
     */
    public function getRecentOrdersWithActions(int $limit = 10): array
    {
        $orderIds = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions ORDER BY dCreatedAt DESC LIMIT :limit",
            ['limit' => $limit]
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        return $orderIds;
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
            'bDone' => (bool) $action->bDone,
            'dCreatedAt' => $action->dCreatedAt,
            'dFailedAt' => $action->dFailedAt ?? null,
            'nFailedCount' => (int) ($action->nFailedCount ?? 0),
            'cFailReason' => $action->cFailReason ?? null,
            'dProcessedAt' => $action->dProcessedAt ?? null,
            'data' => $data
        ];
    }


}
