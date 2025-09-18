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
                    $messages[] = ['type' => 'danger', 'text' => $result['message']];
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
                $csvHelper = new CSVHelper();
                $filePath = $_FILES['csv_file']['tmp_name'];

                try {
                    $parsedData = $csvHelper->parseCsv($filePath);

                    if (empty($parsedData)) {
                        $messages[] = ['type' => 'warning', 'text' => __('CSV file is empty or could not be parsed.')];
                    } else {
                        $lineCount = count($parsedData);
                        $debugInfo = $this->generateCsvDebugInfo($parsedData);
                        $messages[] = ['type' => 'success', 'text' => sprintf(__('CSV file parsed successfully. Found %d data rows.%s'), $lineCount, $debugInfo)];
                    }
                } catch (\Exception $e) {
                    $messages[] = ['type' => 'danger', 'text' => sprintf(__('Error parsing CSV file: %s'), $e->getMessage())];
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
        $shippedNoInvoice = $this->getShippedOrdersWithoutInvoice();
        $totalInvoiceActions = $this->getTotalInvoiceActions();
        $failedInvoiceActions = $this->getFailedInvoiceActions();
        
        return [
            'total_invoices' => $totalInvoiceActions,
            'pending_invoices' => count($shippedNoInvoice),
            'sent_invoices' => $totalInvoiceActions - $failedInvoiceActions,
            'failed_invoices' => $failedInvoiceActions,
            'recent_invoices' => $this->formatOrdersForDisplay($shippedNoInvoice)
        ];
    }

    private function getShippedOrdersWithoutInvoice(): array
    {
        $sql = "
            SELECT DISTINCT shipped.kBestellung
            FROM axytos_actions shipped
            WHERE shipped.cAction = :shippedAction 
              AND NOT EXISTS (
                  SELECT 1 FROM axytos_actions invoice 
                  WHERE invoice.kBestellung = shipped.kBestellung 
                    AND invoice.cAction = :invoiceAction
              )
            ORDER BY shipped.kBestellung DESC
            LIMIT 50
        ";
        
        $orderIds = $this->db->getArrays($sql, [
            'shippedAction' => 'shipped',
            'invoiceAction' => 'invoice'
        ]);

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

    private function formatDate($dateString): string
    {
        if (empty($dateString)) {
            return 'Unknown';
        }

        try {
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return htmlspecialchars($dateString);
        }
    }

    private function formatCurrency($amount, $currency): string
    {
        return number_format((float)$amount, 2, ',', '.') . ' ' . $currency;
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

    private function generateCsvDebugInfo(array $parsedData): string
    {
        $debugInfo = "\n\n<strong>Debug Info:</strong>\n";

        // Show column headers
        if (!empty($parsedData)) {
            $firstRow = reset($parsedData);
            $columns = array_keys($firstRow);
            $debugInfo .= "<strong>Columns:</strong> " . implode(', ', $columns) . "\n";

            // Show first 3 rows as preview
            $debugInfo .= "<strong>Preview (first 3 rows):</strong>\n";
            $previewRows = array_slice($parsedData, 0, 3);
            foreach ($previewRows as $index => $row) {
                $debugInfo .= "Row " . ($index + 1) . ": " . json_encode($row) . "\n";
            }

            // Show total columns and rows info
            $debugInfo .= "<strong>Total:</strong> " . count($parsedData) . " rows, " . count($columns) . " columns";
        }

        return $debugInfo;
    }
}