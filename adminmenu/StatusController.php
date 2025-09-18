<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Checkout\Bestellung;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\helpers\ActionHandler;
use Plugin\axytos_payment\helpers\Utils;
use Plugin\axytos_payment\helpers\CronHelper;

class StatusController
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;
    private ActionHandler $actionHandler;
    private CronHelper $cronHelper;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
        $this->actionHandler = $this->method->createActionHandler();
        $this->cronHelper = new CronHelper();
    }

    public function render(string $tabname, int $menuID, JTLSmarty $smarty): string
    {
        $messages = [];
        
        // Handle form submissions
        if (Request::postInt('save_status') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $messages[] = ['type' => 'info', 'text' => __('Status data refreshed.')];
        }

        if (Request::postInt('process_pending') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $results = $this->processPendingActions();
            $messages = array_merge($messages, $results);
        }

        if (Request::postInt('remove_action') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $orderId = Request::postInt('order_id');
            $actionName = Request::postVar('action_name', '');
            if ($orderId > 0 && !empty($actionName)) {
                $results = $this->removeBrokenAction($orderId, $actionName);
                $messages = array_merge($messages, $results);
            }
        }
        if (Request::postInt('retry_broken') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $orderId = Request::postInt('order_id');
            if ($orderId > 0) {
                $results = $this->retryBrokenActionsForOrder($orderId);
                $messages = array_merge($messages, $results);
            }
        }

        if (Request::postInt('reset_stuck_cron') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $results = $this->resetStuckCronJobs();
            $messages = array_merge($messages, $results);
        }

        // Handle GET parameter for order search AFTER all POST actions (no CSRF token needed for read operations)
        $orderIdOrNumber = trim(Request::getVar('order_search', ''));
        if (!empty($orderIdOrNumber)) {
            $smarty->assign('defaultTabbertab', $menuID);
            $orderDetails = $this->searchOrderDetails($orderIdOrNumber);
            $smarty->assign('searchResult', $orderDetails);
            if (!$orderDetails) {
                $messages[] = ['type' => 'warning', 'text' => __('Order not found or not using Axytos payment method.')];
            }
        }

        // Get status overview data AFTER processing all actions
        $statusOverview = $this->getStatusOverview();
        $unifiedActions = $this->getUnifiedActionsTable(50);
        $recentOrders = empty($unifiedActions) ? $this->getRecentOrdersWithActions(10) : [];

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('statusOverview', $statusOverview);
        $smarty->assign('ordersData', !empty($unifiedActions) ? $unifiedActions : $recentOrders);
        $smarty->assign('showActions', !empty($unifiedActions)); // Show different header text based on data source
        $smarty->assign('token', Form::getTokenInput());
        
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/status.tpl');
    }

    private function processPendingActions(): array
    {
        $messages = [];
        
        try {
            $results = $this->actionHandler->processAllPendingActions();
            
            if ($results['processed'] > 0) {
                $messages[] = [
                    'type' => 'success',
                    'text' => sprintf(__('Successfully processed %d pending action(s).'), $results['processed'])
                ];
            }

            if ($results['failed'] > 0) {
                $messages[] = [
                    'type' => 'warning',
                    'text' => sprintf(__('%d action(s) failed processing.'), $results['failed'])
                ];
            }

            if ($results['processed'] === 0 && $results['failed'] === 0) {
                $messages[] = [
                    'type' => 'info',
                    'text' => __('No pending actions found to process.')
                ];
            }
            
        } catch (\Exception $e) {
            $messages[] = [
                'type' => 'danger',
                'text' => 'Error processing actions: ' . $e->getMessage()
            ];
        }
        
        return $messages;
    }

    private function retryBrokenActionsForOrder(int $orderId): array
    {
        $messages = [];
        
        try {
            $results = $this->actionHandler->retryBrokenActionsForOrder($orderId);
            
            if ($results['processed'] > 0) {
                $messages[] = [
                    'type' => 'success',
                    'text' => sprintf(__('Successfully retried %d broken action(s) for order #%d.'), $results['processed'], $orderId)
                ];
            }
            if ($results['failed'] > 0) {
                $messages[] = [
                    'type' => 'warning',
                    'text' => sprintf(__('%d action(s) still failed for order #%d.'), $results['failed'], $orderId)
                ];
            }
        } catch (\Exception $e) {
            $messages[] = [
                'type' => 'danger',
                'text' => 'Error retrying actions: ' . $e->getMessage()
            ];
        }
        
        return $messages;
    }

    private function removeBrokenAction(int $orderId, string $actionName): array
    {
        $messages = [];
        
        try {
            $success = $this->actionHandler->removeBrokenAction($orderId, $actionName);
            
            if ($success) {
                $messages[] = [
                    'type' => 'success',
                    'text' => sprintf(__('Successfully removed broken action "%s" for order #%d.'), $actionName, $orderId)
                ];
            } else {
                $messages[] = [
                    'type' => 'warning',
                    'text' => sprintf(__('Action "%s" for order #%d could not be removed (may not be broken or not exist).'), $actionName, $orderId)
                ];
            }
            
        } catch (\Exception $e) {
            $messages[] = [
                'type' => 'danger',
                'text' => 'Error removing action: ' . $e->getMessage()
            ];
        }
        
        return $messages;
    }

    private function resetStuckCronJobs(): array
    {
        $messages = [];
        
        try {
            $results = $this->cronHelper->resetStuckCronJobs();
            
            if ($results['found_count'] === 0) {
                $messages[] = [
                    'type' => 'info',
                    'text' => __('No stuck Axytos cron jobs found.')
                ];
            } elseif ($results['reset_count'] > 0) {
                $messages[] = [
                    'type' => 'success',
                    'text' => sprintf(__('Successfully reset %d stuck Axytos cron job(s).'), $results['reset_count'])
                ];
            } else {
                $messages[] = [
                    'type' => 'warning',
                    'text' => __('Failed to reset any stuck cron jobs.')
                ];
            }
            
        } catch (\Exception $e) {
            $messages[] = [
                'type' => 'danger',
                'text' => 'Error resetting stuck cron jobs: ' . $e->getMessage()
            ];
        }
        
        return $messages;
    }

    private function searchOrderDetails(string $orderIdOrNumber): ?array
    {
        if (empty($orderIdOrNumber)) {
            return null;
        }

        // Try to find order by ID first, then by order number
        $order = null;
        if (is_numeric($orderIdOrNumber)) {
            $order = new Bestellung((int)$orderIdOrNumber);
            $order->fuelleBestellung(false);
            if ($order->kBestellung === 0) {
                $order = null;
            }
        }

        // If not found by ID, try by order number using Utils
        if (!$order) {
            try {
                $order = Utils::loadOrderByOrderNumber($this->db, $orderIdOrNumber);
            } catch (\Exception $e) {
                // Order exists but is not an Axytos order - return null to show warning message
                return null;
            }
        }

        if (!$order || $order->kBestellung === 0) {
            return null;
        }

        // Get compact timeline with expandable logs
        $timeline = $this->actionHandler->getCompactTimeline($order->kBestellung);
        $timeline = $this->addStatusFieldsToTimeline($timeline);

        // Also get separate action data for the action buttons (backward compatibility)
        $pendingAndBrokenActions = $this->actionHandler->getPendingAndBrokenActions($order->kBestellung);
        $pendingAndBrokenActions = $this->addStatusFieldsToActions($pendingAndBrokenActions);

        // Extract customer info
        $customerInfo = $this->getCustomerInfo($order);

        return [
            'order' => $order,
            'customerName' => $customerInfo['name'],
            'customerEmail' => $customerInfo['email'],
            'timeline' => $timeline, // Unified timeline with actions and logs
            'pendingActions' => $pendingAndBrokenActions // Keep for action buttons (backward compatibility)
        ];
    }

    private function getStatusOverview(): array
    {
        // Get action counts from ActionHandler
        $actionOverview = $this->actionHandler->getStatusOverview();

        // Get actual cron run times from JTL's cron system via CronHelper
        $lastCronRun = $this->cronHelper->getLastCronRun();
        $nextCronRun = $this->cronHelper->getNextCronRun();
        $cronStatus = $this->cronHelper->getCronStatus();

        return array_merge($actionOverview, [
            'last_cron_run' => $lastCronRun,
            'next_cron_run' => $nextCronRun,
            'cron_status' => $cronStatus
        ]);
    }

    private function getRecentOrdersWithActions(int $limit): array
    {
        $orderIds = $this->actionHandler->getRecentOrdersWithActions($limit);

        $orders = [];
        foreach ($orderIds as $orderId) {
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            
            $customerInfo = $this->getCustomerInfo($order);
            
            // Get detailed actions breakdown via ActionHandler (same as unified actions)
            $actionsBreakdown = $this->actionHandler->getOrderActionsBreakdown($orderId);

            $orders[] = array_merge($actionsBreakdown, [
                'order' => $order,
                'customerName' => $customerInfo['name'],
                'customerEmail' => $customerInfo['email']
            ]);
        }

        return $orders;
    }











    private function getCustomerInfo($order): array
    {
        $name = 'N/A';
        $email = '';

        if ($order->oKunde) {
            $name = trim(($order->oKunde->cVorname ?? '') . ' ' . ($order->oKunde->cNachname ?? ''));
            $email = $order->oKunde->cMail ?? '';
        } elseif ($order->oRechnungsadresse) {
            $name = trim(($order->oRechnungsadresse->cVorname ?? '') . ' ' . ($order->oRechnungsadresse->cNachname ?? ''));
            $email = $order->oRechnungsadresse->cMail ?? '';
        }

        if (empty($name) || $name === ' ') {
            $name = 'N/A';
        }

        return [
            'name' => $name,
            'email' => $email
        ];
    }

    private function getUnifiedActionsTable(int $limit): array
    {
        // Get all orders with any actions (pending or broken) via ActionHandler
        $orderIds = $this->actionHandler->getOrdersWithActions($limit);

        $ordersWithActions = [];
        foreach ($orderIds as $orderId) {
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            
            $customerInfo = $this->getCustomerInfo($order);
            
            // Get detailed actions breakdown via ActionHandler
            $actionsBreakdown = $this->actionHandler->getOrderActionsBreakdown($orderId);

            // Only include orders that have actions
            if ($actionsBreakdown['total_actions'] > 0) {
                $ordersWithActions[] = array_merge($actionsBreakdown, [
                    'order' => $order,
                    'customerName' => $customerInfo['name'],
                    'customerEmail' => $customerInfo['email']
                ]);
            }
        }

        return $ordersWithActions;
    }

    /**
     * Add pre-computed status fields to action arrays using ActionHandler's getStatus() method
     */
    private function addStatusFieldsToActions(array $actions): array
    {
        return array_map(function($action) {
            // Convert to array if it's an object for getStatus() method
            $actionArray = is_object($action) ? (array) $action : $action;

            // Get the computed status using ActionHandler's logic
            $status = $this->actionHandler->getStatus($actionArray);

            // Add status fields
            $statusData = $this->getStatusData($status, $actionArray);
            if (is_object($action)) {
                $action->status = $status;
                $action->statusText = $statusData['text'];
                $action->statusKey = $statusData['key'];
                $action->statusParams = $statusData['params'];
                $action->statusColor = $this->getStatusColor($status);
            } else {
                $action['status'] = $status;
                $action['statusText'] = $statusData['text'];
                $action['statusKey'] = $statusData['key'];
                $action['statusParams'] = $statusData['params'];
                $action['statusColor'] = $this->getStatusColor($status);
            }

            return $action;
        }, $actions);
    }

    /**
     * Add pre-computed status fields to timeline entries using ActionHandler's getStatus() method
     */
    private function addStatusFieldsToTimeline(array $timeline): array
    {
        return array_map(function($entry) {
            // Create action array for getStatus() method
            $actionArray = [
                'bDone' => $entry['status'] === 'completed',
                'nFailedCount' => $entry['failed_count'] ?? 0,
                'dFailedAt' => $entry['failed_at']
            ];

            // Get the computed status using ActionHandler's logic
            $status = $this->actionHandler->getStatus($actionArray);

            // Add status fields to timeline entry
            $statusData = $this->getStatusData($status, $actionArray);
            $entry['statusText'] = $statusData['text'];
            $entry['statusKey'] = $statusData['key'];
            $entry['statusParams'] = $statusData['params'];
            $entry['statusColor'] = $this->getStatusColor($status);

            return $entry;
        }, $timeline);
    }



    /**
     * Get status data including translation key and parameters
     */
    private function getStatusData(string $status, array $actionArray): array
    {
        switch ($status) {
            case 'completed':
                return [
                    'text' => 'completed',
                    'key' => 'completed',
                    'params' => []
                ];
            case 'broken':
                $failedCount = $actionArray['nFailedCount'] ?? 0;
                if ($failedCount > 0) {
                    return [
                        'text' => "broken (failed {$failedCount}x)",
                        'key' => 'broken (failed %dx)',
                        'params' => [$failedCount]
                    ];
                } else {
                    return [
                        'text' => 'broken',
                        'key' => 'broken',
                        'params' => []
                    ];
                }
            case 'pending':
                if (empty($actionArray['dFailedAt'])) {
                    return [
                        'text' => 'pending',
                        'key' => 'pending',
                        'params' => []
                    ];
                } else {
                    $failedCount = $actionArray['nFailedCount'] ?? 0;
                    if ($failedCount > 0) {
                        return [
                            'text' => "retry (failed {$failedCount}x)",
                            'key' => 'retry (failed %dx)',
                            'params' => [$failedCount]
                        ];
                    } else {
                        return [
                            'text' => 'retry',
                            'key' => 'retry',
                            'params' => []
                        ];
                    }
                }
            default:
                return [
                    'text' => $status,
                    'key' => $status,
                    'params' => []
                ];
        }
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor(string $status): string
    {
        switch ($status) {
            case 'completed':
                return '#28a745'; // green
            case 'broken':
                return '#dc3545'; // red
            case 'pending':
                return '#fd7e14'; // orange
            default:
                return '#6c757d'; // gray
        }
    }
}
