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

class StatusHandler
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;
    private ActionHandler $actionHandler;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
        $this->actionHandler = $this->method->createActionHandler();
    }

    public function render(string $tabname, int $menuID, JTLSmarty $smarty): string
    {
        $messages = [];
        
        // Handle form submissions
        if (Request::postInt('process_pending') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $results = $this->processPendingActions();
            $messages = array_merge($messages, $results);
        }

        if (Request::postInt('retry_broken') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $orderId = Request::postInt('order_id');
            if ($orderId > 0) {
                $results = $this->retryBrokenActionsForOrder($orderId);
                $messages = array_merge($messages, $results);
            }
        }

        if (Request::postInt('search_order') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $orderIdOrNumber = trim(Request::postVar('order_search', ''));
            $orderDetails = $this->searchOrderDetails($orderIdOrNumber);
            $smarty->assign('searchResult', $orderDetails);
            if (!$orderDetails) {
                $messages[] = ['type' => 'warning', 'text' => 'Order not found or not using Axytos payment method.'];
            }
        }

        // Get status overview data
        $statusOverview = $this->getStatusOverview();
        $recentOrders = $this->getRecentOrdersWithActions(10);
        $brokenOrders = $this->getBrokenOrders(5);

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('statusOverview', $statusOverview);
        $smarty->assign('recentOrders', $recentOrders);
        $smarty->assign('brokenOrders', $brokenOrders);
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
                    'text' => sprintf('Successfully processed %d pending action(s).', $results['processed'])
                ];
            }
            
            if ($results['failed'] > 0) {
                $messages[] = [
                    'type' => 'warning', 
                    'text' => sprintf('%d action(s) failed processing.', $results['failed'])
                ];
            }
            
            if ($results['processed'] === 0 && $results['failed'] === 0) {
                $messages[] = [
                    'type' => 'info',
                    'text' => 'No pending actions found to process.'
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
                    'text' => sprintf('Successfully retried %d broken action(s) for order #%d.', $results['processed'], $orderId)
                ];
            }
            
            if ($results['failed'] > 0) {
                $messages[] = [
                    'type' => 'warning',
                    'text' => sprintf('%d action(s) still failed for order #%d.', $results['failed'], $orderId)
                ];
            }
            
            if ($results['total_broken'] === 0) {
                $messages[] = [
                    'type' => 'info',
                    'text' => sprintf('No broken actions found for order #%d.', $orderId)
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

        // If not found by ID, try by order number
        if (!$order) {
            $result = $this->db->select('tbestellung', 'cBestellNr', $orderIdOrNumber);
            if ($result) {
                $order = new Bestellung((int)$result->kBestellung);
                $order->fuelleBestellung(false);
            }
        }

        if (!$order || $order->kBestellung === 0) {
            return null;
        }

        // Check if it's an Axytos order
        $axytosPaymentId = Utils::getPaymentMethodId($this->db);
        if ($order->kZahlungsart !== $axytosPaymentId) {
            return null;
        }

        $allActions = $this->actionHandler->getAllActions($order->kBestellung);
        $actionLogs = $this->actionHandler->getActionLogs($order->kBestellung);

        return [
            'order' => $order,
            'pendingActions' => $allActions['pending'],
            'completedActions' => $allActions['completed'],
            'actionLogs' => $actionLogs
        ];
    }

    private function getStatusOverview(): array
    {
        // Get counts for different action states
        $pendingCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE cStatus = 'pending'"
        );
        
        $brokenCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions WHERE cStatus = 'failed'"
        );
        
        $totalOrdersCount = $this->db->getSingleObject(
            "SELECT COUNT(DISTINCT kBestellung) as count FROM axytos_actions"
        );

        // Get last cron run (latest action that was processed)
        $lastCronRun = $this->db->getSingleObject(
            "SELECT MAX(dProcessedAt) as last_run FROM axytos_actions WHERE cStatus = 'completed' AND dProcessedAt IS NOT NULL"
        );

        // Get next cron run from JTL's cron system
        $nextCronRun = $this->getNextCronRun();

        return [
            'pending_orders' => (int) ($pendingCount->count ?? 0),
            'broken_orders' => (int) ($brokenCount->count ?? 0),
            'total_orders' => (int) ($totalOrdersCount->count ?? 0),
            'last_cron_run' => $lastCronRun->last_run ?? null,
            'next_cron_run' => $nextCronRun
        ];
    }

    private function getRecentOrdersWithActions(int $limit): array
    {
        $orderIds = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions ORDER BY dCreatedAt DESC LIMIT $limit"
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        $orders = [];
        foreach ($orderIds as $orderId) {
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            
            $allActions = $this->actionHandler->getAllActions($orderId);
            
            $orders[] = [
                'order' => $order,
                'pending_count' => count($allActions['pending']),
                'completed_count' => count($allActions['completed']),
                'has_broken' => $this->hasOrderBrokenActions($orderId)
            ];
        }

        return $orders;
    }

    private function getBrokenOrders(int $limit): array
    {
        $orderIds = $this->db->getCollection(
            "SELECT DISTINCT kBestellung FROM axytos_actions WHERE cStatus = 'failed' ORDER BY dFailedAt DESC LIMIT $limit"
        )->map(static function ($row): int {
            return (int) $row->kBestellung;
        })->toArray();

        $orders = [];
        foreach ($orderIds as $orderId) {
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            
            $brokenActions = $this->db->selectAll(
                'axytos_actions',
                ['kBestellung', 'cStatus'],
                [$orderId, 'failed'],
                'cAction, nFailedCount, cFailReason, dFailedAt'
            );
            
            $orders[] = [
                'order' => $order,
                'broken_actions' => $brokenActions
            ];
        }

        return $orders;
    }

    private function hasOrderBrokenActions(int $orderId): bool
    {
        $brokenAction = $this->db->select(
            'axytos_actions',
            ['kBestellung', 'cStatus'],
            [$orderId, 'failed']
        );
        
        return $brokenAction !== null;
    }

    private function formatActionTime($timeString): string
    {
        if (empty($timeString)) {
            return 'Unknown';
        }

        try {
            $date = new \DateTime($timeString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return htmlspecialchars($timeString);
        }
    }

    public function formatActionStatus(array $action): string
    {
        if (empty($action['dFailedAt'])) {
            return 'pending';
        }

        if ($this->actionHandler->isBroken($action)) {
            return sprintf('failed %dx, broken', $action['nFailedCount']);
        } else {
            return sprintf('failed %dx, will retry', $action['nFailedCount']);
        }
    }

    private function getNextCronRun(): ?string
    {
        // Get the next scheduled run for Axytos cron jobs from JTL's cron system
        $cronJob = $this->db->getSingleObject(
            "SELECT dNaechsterLauf FROM tcron WHERE cKey LIKE '%axytos%' ORDER BY dNaechsterLauf ASC LIMIT 1"
        );
        
        return $cronJob->dNaechsterLauf ?? null;
    }
}