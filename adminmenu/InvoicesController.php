<?php

namespace Plugin\axytos_payment\adminmenu;

use JTL\Smarty\JTLSmarty;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Checkout\Bestellung;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\helpers\Utils;
use Plugin\axytos_payment\helpers\CSVHelper;
use Plugin\axytos_payment\helpers\InvoiceUpdatesHandler;

class InvoicesController
{
    private PluginInterface $plugin;
    private AxytosPaymentMethod $method;
    private DbInterface $db;

    public function __construct(PluginInterface $plugin, AxytosPaymentMethod $method, DbInterface $db)
    {
        $this->plugin = $plugin;
        $this->method = $method;
        $this->db = $db;
    }

    public function render(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $messages = [];
        
        // Handle form submissions
        if (Request::postInt('save_invoices') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);
            $messages[] = ['type' => 'info', 'text' => __('Invoice data refreshed.')];
        }
        
        // Handle invoice number setting
        if (Request::postInt('set_invoice_number') === 1 && Form::validateToken()) {
            $orderId = Request::postInt('order_id');
            $invoiceNumber = Request::postVar('invoice_number');

            if ($orderId > 0 && !empty($invoiceNumber)) {
                $result = $this->setInvoiceNumber($orderId, $invoiceNumber);
                if ($result['success']) {
                    $smarty->assign('defaultTabbertab', $menuID);
                    $messages[] = ['type' => 'success', 'text' => sprintf(__('Invoice number %s has been set for order %s'), $invoiceNumber, $result['orderNumber'])];
                 } else {
                     $errorMessage = $result['message'];
                     $messages[] = ['type' => 'danger', 'text' => $errorMessage];
                 }
            } else {
                $messages[] = ['type' => 'danger', 'text' => __('Invalid order ID or empty invoice number.')];
            }
        }

        // Handle CSV upload
        if (Request::postInt('upload_csv') === 1 && Form::validateToken()) {
            $smarty->assign('defaultTabbertab', $menuID);

            // Check if file was uploaded
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $_FILES['csv_file']['tmp_name'];
                $originalFilename = $_FILES['csv_file']['name'];

                try {
                    $invoiceHandler = new InvoiceUpdatesHandler($this->method, $this->db);

                    // Validate CSV structure
                    $isValid = $invoiceHandler->validateCSVStructure($filePath, $originalFilename);
                    if (!$isValid) {
                        $messages[] = ['type' => 'danger', 'text' => __('CSV file structure is invalid. Please check the file format.')];
                    } else {
                        // Parse and normalize CSV data
                        $csvHelper = new CSVHelper();
                        $parsedData = $csvHelper->parseCsv($filePath, originalFilename: $originalFilename);
                        $normalizedData = $invoiceHandler->normalizeCSV($parsedData);

                        if (empty($normalizedData)) {
                            $messages[] = ['type' => 'warning', 'text' => __('CSV file is empty or could not be parsed.')];
                        } else {
                            // Process invoice updates
                            $processResult = $invoiceHandler->processInvoiceIdsUpdate($normalizedData);

                            $successfulCount = $processResult['successful_count'];
                            $errorCount = $processResult['error_count'];
                            $totalProcessed = $processResult['total_processed'];

                            if ($successfulCount > 0) {
                                $messages[] = ['type' => 'success', 'text' => sprintf(__('Successfully processed %d invoice updates out of %d total rows.'), $successfulCount, $totalProcessed)];
                            }

                            if ($errorCount > 0) {
                                $messages[] = ['type' => 'warning', 'text' => sprintf(__('%d rows failed to process. Check the details below.'), $errorCount)];
                            }

            // Add user-friendly messages to results for template display
            $resultsWithMessages = array_map(function($result) {
                $message = '';
                switch ($result['status']) {
                    case 'success':
                        $message = __('Invoice number added successfully');
                        break;
                    case 'skipped':
                        $existingInvoice = $result['invoiceNumber'] ?? 'N/A';
                        $message = sprintf(__('Order already has invoice number: %s'), $existingInvoice);
                        break;
                    case 'error':
                        $message = $result['error'] ?? __('Unknown error occurred');
                        break;
                    default:
                        $message = __('Processing completed');
                }
                $result['message'] = $message;
                return $result;
            }, $processResult['results']);

            // Always show detailed processing information
            $smarty->assign('processingResults', $resultsWithMessages);
                        }
                    }
                } catch (\Exception $e) {
                    $messages[] = ['type' => 'danger', 'text' => sprintf(__('Error processing CSV file: %s'), $e->getMessage())];
                }
            } else {
                $messages[] = ['type' => 'danger', 'text' => __('No CSV file was uploaded or upload failed.')];
            }
        }

        // Get invoice data for display
        $invoicesData = $this->getInvoicesData();

        // Assign variables to template
        $smarty->assign('messages', $messages);
        $smarty->assign('invoicesData', $invoicesData);
        $smarty->assign('token', Form::getTokenInput());
        
        return $smarty->fetch($this->plugin->getPaths()->getAdminPath() . 'template/invoices.tpl');
    }

    private function getInvoicesData(): array
    {
        $ordersAwaitingInvoice = $this->getOrdersAwaitingInvoice();
        $totalInvoiceActions = $this->getTotalInvoiceActions();
        $failedInvoiceActions = $this->getFailedInvoiceActions();

        return [
            'total_invoices' => $totalInvoiceActions,
            'pending_invoices' => count($ordersAwaitingInvoice),
            'sent_invoices' => $totalInvoiceActions - $failedInvoiceActions,
            'failed_invoices' => $failedInvoiceActions,
            'orders_awaiting_invoice' => $this->formatOrdersForDisplay($ordersAwaitingInvoice)
        ];
    }

    private function getOrdersAwaitingInvoice(): array
    {
        $sql = "
            SELECT DISTINCT a.kBestellung
            FROM axytos_actions a
            WHERE a.cAction IN ('confirm', 'shipped', 'reverse_cancel')
              AND a.dProcessedAt = (
                  SELECT MAX(a2.dProcessedAt)
                  FROM axytos_actions a2
                  WHERE a2.kBestellung = a.kBestellung
              )
            ORDER BY a.kBestellung DESC
            LIMIT 50
        ";

        $orderIds = $this->db->getArrays($sql, []);

        $orders = [];
        foreach ($orderIds as $row) {
            $order = new Bestellung((int)$row['kBestellung']);
            $order->fuelleBestellung(false);
            $orders[] = $order;
        }

        return $orders;
    }

    private function getTotalInvoiceActions(): int
    {
        $sql = "SELECT COUNT(*) as count FROM axytos_actions WHERE cAction = :actionName";
        $result = $this->db->getSingleArray($sql, ['actionName' => 'invoice']);
        return (int)($result['count'] ?? 0);
    }

    private function getFailedInvoiceActions(): int
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM axytos_actions 
            WHERE cAction = :actionName 
              AND bDone = FALSE 
              AND nFailedCount > 3
        ";
        $result = $this->db->getSingleArray($sql, ['actionName' => 'invoice']);
        return (int)($result['count'] ?? 0);
    }

    private function formatOrdersForDisplay(array $orders): array
    {
        $formattedOrders = [];
        
        foreach ($orders as $order) {
            $customerInfo = $this->getCustomerInfo($order);
            
            $formattedOrders[] = [
                'order' => $order,
                'customerName' => $customerInfo['name'],
                'customerEmail' => $customerInfo['email']
            ];
        }
        
        return $formattedOrders;
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


    
    private function setInvoiceNumber(int $orderId, string $invoiceNumber): array
    {
        try {
            // Get order details
            $order = new Bestellung($orderId);
            $order->fuelleBestellung(false);
            
            if (!$order->kBestellung) {
                return ['success' => false, 'message' => __('Order not found.')];
            }
            
            // Use the payment method's invoiceWasCreated method
            $this->method->invoiceWasCreated($order->cBestellNr, $invoiceNumber);
            
            return [
                'success' => true,
                'orderNumber' => $order->cBestellNr
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => sprintf(__('Error setting invoice number: %s'), $e->getMessage())];
        }
    }


}
