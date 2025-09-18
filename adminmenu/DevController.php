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
use Plugin\axytos_payment\helpers\DataFormatter;
use Plugin\axytos_payment\helpers\Utils;

class DevController
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;
    private ActionHandler $actionHandler;
    private ?int $axytosPaymentMethodId = null;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
        $this->actionHandler = $this->method->createActionHandler();
        $this->axytosPaymentMethodId = Utils::getPaymentMethodId($this->db);
    }

    public function render(string $tabname, int $menuID, JTLSmarty $smarty): string
    {
        $messages = [];
        
        // Handle add pending action form submission
        if (Request::postInt('add_pending_action') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $messages = array_merge($messages, $this->handleAddPendingAction());
        }

        // Get some basic info to display
        $devInfo = [
            'plugin_version' => $this->plugin->getMeta()->getVersion(),
            'plugin_path' => $this->plugin->getPaths()->getBasePath(),
            'current_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'db_connection' => $this->db ? 'Connected' : 'Not connected',
            'max_retries' => ActionHandler::MAX_RETRIES
        ];

        // Available action types
        $actionTypes = [
            'confirm' => 'Confirm',
            'shipped' => 'Shipped', 
            'invoice' => 'Invoice',
            'cancel' => 'Cancel',
            'reverse_cancel' => 'Reverse Cancel',
            'refund' => 'Refund'
        ];

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('devInfo', $devInfo);
        $smarty->assign('actionTypes', $actionTypes);
        $smarty->assign('token', Form::getTokenInput());
        
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/dev.tpl');
    }

    private function handleAddPendingAction(): array
    {
        $messages = [];
        
        try {
            $orderNumber = trim(Request::postVar('order_number', ''));
            $actionName = Request::postVar('action', '');
            $failedCount = Request::postInt('failed_count', 0);

            // Validate input
            if (empty($orderNumber)) {
                $messages[] = ['type' => 'danger', 'text' => 'Error: Order number is required'];
                return $messages;
            }

            if (empty($actionName)) {
                $messages[] = ['type' => 'danger', 'text' => 'Error: Action name is required'];
                return $messages;
            }

            if ($failedCount < 0 || $failedCount > 10) {
                $messages[] = ['type' => 'danger', 'text' => 'Error: Invalid failed count (must be 0-10)'];
                return $messages;
            }

            // Get the order by order number
            $order = $this->findOrderByNumber($orderNumber);
            
            if (!$order) {
                $messages[] = ['type' => 'danger', 'text' => "Error: Order '{$orderNumber}' not found"];
                return $messages;
            }

            // Check if it's an Axytos order
            if (!$this->isAxytosOrder($order)) {
                $messages[] = ['type' => 'danger', 'text' => "Error: Order '{$orderNumber}' is not using Axytos payment method"];
                return $messages;
            }

            $orderId = $order->kBestellung;

            // Check if action already exists
            $existingActions = $this->actionHandler->getPendingActions($orderId);
            foreach ($existingActions as $existingAction) {
                if ($existingAction['cAction'] === $actionName) {
                    $messages[] = ['type' => 'warning', 'text' => "Action '{$actionName}' already exists for order '{$orderNumber}' (ID: {$orderId})"];
                    return $messages;
                }
            }

            // Create the test action directly in database
            $success = $this->createTestAction($orderId, $actionName, $failedCount);
            
            if ($success) {
                $actionState = $this->getActionStateDescription($failedCount);
                $willShowRemove = $failedCount > ActionHandler::MAX_RETRIES;
                
                $messages[] = ['type' => 'success', 'text' => 
                    "✓ Successfully added action '{$actionName}' to order '{$orderNumber}' (ID: {$orderId})<br>" .
                    "• failed_count: {$failedCount} (MAX_RETRIES=" . ActionHandler::MAX_RETRIES . ")<br>" .
                    "• State: {$actionState}" . 
                    ($willShowRemove ? "<br>• This action will show a \"Retry\" button in the admin interface" : "")
                ];

                // Add order note via action log
                $this->actionHandler->addActionLog(
                    $orderId, 
                    $actionName, 
                    'info', 
                    "Test: Added action '{$actionName}' with failed_count={$failedCount} ({$actionState}) for testing purposes"
                );
                
            } else {
                $messages[] = ['type' => 'danger', 'text' => 'Error: Failed to create test action'];
            }
            
        } catch (\Exception $e) {
            $messages[] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        }
        
        return $messages;
    }

    private function isAxytosOrder(Bestellung $order): bool
    {
        return $order->kBestellung 
            && $this->axytosPaymentMethodId 
            && $order->kZahlungsart === $this->axytosPaymentMethodId;
    }

    private function createTestAction(int $orderId, string $actionName, int $failedCount): bool
    {
        // Generate realistic test data based on action type
        $testData = $this->generateTestDataForAction($orderId, $actionName);
        
        $insertData = new \stdClass();
        $insertData->kBestellung = $orderId;
        $insertData->cAction = $actionName;
        $insertData->bDone = false;
        $insertData->dCreatedAt = date('Y-m-d H:i:s');
        $insertData->dFailedAt = $failedCount > 0 ? date('Y-m-d H:i:s') : null;
        $insertData->nFailedCount = $failedCount;
        $insertData->cFailReason = $failedCount > 0 ? "Test action created with failed_count={$failedCount}" : null;
        $insertData->dProcessedAt = null;
        $insertData->cData = json_encode($testData);

        return $this->db->insert('axytos_actions', $insertData) > 0;
    }

    private function getActionStateDescription(int $failedCount): string
    {
        if ($failedCount == 0) {
            return 'Fresh pending action';
        } elseif ($failedCount < ActionHandler::MAX_RETRIES) {
            return 'Retry-able failed action';
        } else {
            return 'Broken action (will show retry button)';
        }
    }

    private function generateTestDataForAction(int $orderId, string $actionName): array
    {
        // Load the order to generate realistic data
        $order = new Bestellung($orderId);
        $order->fuelleBestellung(false);
        
        $dataFormatter = new DataFormatter($order);
        
        switch ($actionName) {
            case 'confirm':
                // For confirm, we need both order data and precheck response
                // Since this is for testing, we'll create a mock precheck response
                $orderData = $dataFormatter->createOrderData();
                $mockPrecheckResponse = [
                    'decision' => 'U',
                    'token' => 'TEST_TOKEN_' . uniqid(),
                    'timestamp' => date('c')
                ];
                return $dataFormatter->createConfirmData($mockPrecheckResponse, $orderData);
                
            case 'shipped':
                return $dataFormatter->createShippingData();
                
            case 'invoice':
                return $dataFormatter->createInvoiceData();
                
            case 'cancel':
            case 'reverse_cancel':
                return [
                    'externalOrderId' => $dataFormatter->getExternalOrderId()
                ];
                
            case 'refund':
                // Refund method is commented out in DataFormatter, so we'll create basic refund data
                return [
                    'externalOrderId' => $dataFormatter->getExternalOrderId(),
                    'refundDate' => date('c'),
                    'originalInvoiceNumber' => $order->cBestellNr,
                    'externalSubOrderId' => ''
                ];
                
            default:
                return [
                    'externalOrderId' => $dataFormatter->getExternalOrderId(),
                    'action' => $actionName,
                    'testData' => true
                ];
        }
    }

    private function findOrderByNumber(string $orderNumber): ?Bestellung
    {
        if (empty($orderNumber)) {
            return null;
        }

        // Try to find order by order number using Utils
        try {
            $order = Utils::loadOrderByOrderNumber($this->db, $orderNumber);
            if ($order && $order->kBestellung > 0) {
                return $order;
            }
        } catch (\Exception $e) {
            // Order exists but is not Axytos order
            return null;
        }

        // If not found by order number, try as order ID (fallback for numeric input)
        if (is_numeric($orderNumber)) {
            $order = new Bestellung((int)$orderNumber);
            $order->fuelleBestellung(false);
            
            if ($order->kBestellung > 0) {
                return $order;
            }
        }

        return null;
    }
}