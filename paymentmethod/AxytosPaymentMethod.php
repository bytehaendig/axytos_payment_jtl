<?php

namespace Plugin\axytos_payment\paymentmethod;

use JTL\Plugin\Data\PaymentMethod;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Checkout\Bestellung;
use JTL\Checkout\OrderHandler;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Cart\Cart;
use Plugin\axytos_payment\helpers\ApiClient;
use Plugin\axytos_payment\helpers\DataFormatter;
use Plugin\axytos_payment\helpers\ActionHandler;
use Plugin\axytos_payment\helpers\Utils;
use stdClass;

class AxytosPaymentMethod extends Method
{
    private PluginInterface $plugin;
    private ?PaymentMethod $method;
    private DbInterface $db;
    private array $settings;

    /**** START Payment Method Interface  */

    /** overwrite */
    public function init(int $nAgainCheckout = 0): self
    {
        parent::init($nAgainCheckout);

        $pluginID     = PluginHelper::getIDByModuleID($this->moduleID);
        $this->plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
        $this->db = Shop::Container()->getDB();
        $this->loadPluginSettings();

        return $this;
    }

    /** overwrite */
    public function getSetting(string $key): mixed
    {
        $setting = parent::getSetting($key);
        $name = $this->getSettingName($key);
        if ($setting === null && array_key_exists($name, $this->settings)) {
            $setting = $this->settings[$name];
        }
        return $setting;
    }

    /** overwrite */
    public function isValidIntern(array $args_arr = []): bool
    {
        $emptyApiKey = empty($this->getSetting('api_key'));
        if ($emptyApiKey) {
            return false;
        }
        return parent::isValidIntern($args_arr) && $this->duringCheckout === 1;
    }

    public function isValid(object $customer, Cart $cart): bool
    {
        return parent::isValid($customer, $cart);
    }

    public function isSelectable(): bool
    {
        if (!parent::isSelectable()) {
            return false;
        }
        $rejected = $this->getCache('axytos_precheck_rejected');
        return $rejected !== '1';
    }

    /** overwrite */
    public function preparePaymentProcess(Bestellung $order): void
    {
        assert($this->duringCheckout === 1);
        parent::preparePaymentProcess($order);
        $client       = $this->createApiClient();
        $dataFormatter = $this->createDataFormatter($order);
        $orderData = $dataFormatter->createOrderData($order);
        $precheckData  =  $dataFormatter->createPrecheckData($orderData);
        // we need to cache the order-data to re-use it during confirm
        // because of a JTL bug where order-data is not exactly the same before
        // and after perstistence to Db
        // but Axytos REQUIRES exactly the same data in precheck and confirm
        $this->addCache('order_data', json_encode($orderData));
        $precheckSuccessful = false;
        $paymentAccepted = false;
        try {
            $precheckResponse = $client->precheck($precheckData);
            $this->addCache('precheck_response', $precheckResponse);
            $precheckSuccessful = true;
        } catch (\Exception $e) {
            $this->doLog("precheck failed: " . $e->getMessage(), \LOGLEVEL_ERROR);
            $this->getLogger()->error(
                'Axytos payment precheck failed for order {kBestellung}: {message}',
                ['kBestellung' => $order->kBestellung, 'message' => $e->getMessage()],
            );
            $this->addErrorMessage('error_order_precheck_failed', 'precheck_error');
        }
        if ($precheckSuccessful) {
            $responseBody = json_decode($precheckResponse, true);
            $decisionCode = $responseBody['decision'];
            $paymentAccepted = strtolower($decisionCode) === "u";
            if (!$paymentAccepted) {
                $this->addErrorMessage('error_payment_rejected', 'precheck_rejected');
                $this->getLogger()->info(
                    'Axytos payment precheck rejected for order {kBestellung}',
                    ['kBestellung' => $order->kBestellung],
                );
                $this->addCache('axytos_precheck_rejected', '1');
            } else {
                $this->getLogger()->info(
                    'Axytos payment precheck accepted for order {kBestellung}.',
                    ['kBestellung' => $order->kBestellung],
                );
            }
        }
        $redirectUrl = ($paymentAccepted)
        ? $this->getNotificationURL($this->generateHash($order))
        : Shop::getURL() . '/bestellvorgang.php?editZahlungsart=1';
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function finalizeOrder(Bestellung $order, string $hash, array $args): bool
    {
        // called after redirect to getNotificationURL
        // order is not yet saved to DB
        // true means payment success
        // this is a workaround since JTL does not create a 'nice' order number due to some bug
        $handler = new OrderHandler($this->db, Frontend::getCustomer(), Frontend::getCart());
        $order->cBestellNr = $handler->createOrderNo();
        return true;
    }

    public function redirectOnPaymentSuccess(): bool
    {
        // called when finalizeOrder returns true
        return true;
    }

    public function redirectOnCancel(): bool
    {
        // called when finalizeOrder returns false
        return true;
    }

    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        // called after redirect to getNotificationURL and after finalizeOrder
        // order is saved to DB - now we can save additional information
        //
        // it is misleading for the customer when the order is already marked as paid
        // TODO: do this later in a cron job (periodically check payment status of orders)
        //
        // $this->addIncomingPayment($order, (object)[
        //     'fBetrag'          => $order->fGesamtsumme,
        //     'fZahlungsgebuehr' => 0,
        //     'cHinweis'         => 'Axytos', // add transaction ID
        // ]);
        // $this->setOrderStatusToPaid($order);
        // $this->sendConfirmationMail($order);
        $orderDataRaw = $this->getCache('order_data');
        $orderData = $orderDataRaw ? json_decode($orderDataRaw, true) : null;
        $precheckResponse = $this->getCache('precheck_response');
        $precheckResponseJson = json_decode($precheckResponse, true);
        $dataFormatter = $this->createDataFormatter($order);
        if ($orderData === null) {
            $this->getLogger()->warning(
                'no cached order-data from precheck for order {kBestellung}, recreate order-data (might lead to subtle differences and Axytos failure)',
                ['kBestellung' => $order->kBestellung],
            );
            $orderData = $dataFormatter->createOrderData();
        }
        $confirmData  = $dataFormatter->createConfirmData($precheckResponseJson, $orderData);
        $orderId = $order->kBestellung;
        $actionHandler = $this->createActionHandler();
        $actionHandler->addPendingAction($orderId, 'confirm', $confirmData);
        $successful = $actionHandler->processPendingActionsForOrder($orderId);
        if ($successful) {
            $this->addSuccessMessage('payment_accepted');
        } else {
            // TODO: what to do in case of error - does a user error make any sense?
            $this->addErrorMessage('error_order_confirmation_failed', 'confirmation_error');
        }
    }

    public function cancelOrder(int $orderId, bool $delete = false): void
    {
        parent::cancelOrder($orderId, $delete);
        $order = new Bestellung($orderId);
        $order->fuelleBestellung(false);
        $dataFormatter = $this->createDataFormatter($order);
        $externalOrderId = $dataFormatter->getExternalOrderId();
        $actionHandler = $this->createActionHandler();
        $actionHandler->addPendingAction($orderId, 'cancel', ['externalOrderId' => $externalOrderId]);
        $actionHandler->processPendingActionsForOrder($orderId);
    }

    public function reactivateOrder(int $orderId): void
    {
        parent::reactivateOrder($orderId);
        $order = new Bestellung($orderId);
        $order->fuelleBestellung(false);
        $dataFormatter = $this->createDataFormatter($order);
        $externalOrderId = $dataFormatter->getExternalOrderId();
        $actionHandler = $this->createActionHandler();
        $actionHandler->addPendingAction($orderId, 'reverse_cancel', ['externalOrderId' => $externalOrderId]);
        $actionHandler->processPendingActionsForOrder($orderId);
    }

    /**** END Payment Method Interface  */

    public function orderWasShipped(int $orderId): void
    {
        $order = new Bestellung($orderId);
        $order->fuelleBestellung(false);
        $dataFormatter = $this->createDataFormatter($order);
        $shippingData = $dataFormatter->createShippingData();
        // $invoiceData = $dataFormatter->createInvoiceData();
        // $actionHandler->addPendingAction($orderId, 'invoice', $invoiceData);

        $actionHandler = $this->createActionHandler();
        $actionHandler->addPendingAction($orderId, 'shipped', $shippingData);
        $actionHandler->processPendingActionsForOrder($orderId);
    }

    public function invoiceWasCreated(string $orderNumber, string $invoiceNumber = null): void
    {
        // Load the order by order number
        $order = Utils::loadOrderByOrderNumber($this->db, $orderNumber);

        if ($order === null) {
            throw new \Exception("Order with order number '{$orderNumber}' not found");
        }

        $orderId = $order->kBestellung;

        $dataFormatter = $this->createDataFormatter($order);
        $invoiceData = $dataFormatter->createInvoiceData();

        // Store invoice number if provided
        if ($invoiceNumber !== null) {
            $this->addOrderAttribute($order, 'invoice_number', $invoiceNumber);
        }

        $actionHandler = $this->createActionHandler();
        $actionHandler->addPendingAction($orderId, 'invoice', $invoiceData);
        $actionHandler->processPendingActionsForOrder($orderId);
    }

    private function loadPluginSettings(): void
    {
        $this->settings = [];
        $pluginId = $this->plugin->getID();
        $settings = $this->db->selectAll('tplugineinstellungen', ['kPlugin'], [$pluginId]);
        foreach ($settings as $setting) {
            if ($setting->cName === $this->getSettingName('api_key')) {
                $encryption = Shop::Container()->getCryptoService();
                $setting->cWert = trim($encryption->decryptXTEA($setting->cWert));
            }
            if ($setting->cName === $this->getSettingName('use_sandbox')) {
                $setting->cWert = (bool)$setting->cWert;
            }
            $this->settings[$setting->cName] = $setting->cWert;
        }
    }

    public function savePluginSettings(array $settings): bool
    {
        $pluginId = $this->plugin->getID();

        foreach ($settings as $key => $value) {
            // Encrypt API key before saving if not empty
            if ($key === 'api_key' && !empty($value)) {
                $cryptoService = Shop::Container()->getCryptoService();
                $value = $cryptoService->encryptXTEA(trim($value));
            }
            if ($key === 'use_sandbox') {
                $value = (bool)$value;
            }
            $ins = new stdClass();
            $ins->kPlugin = $pluginId;
            $ins->cName = $this->getSettingName($key);
            $ins->cWert = $value;
            $this->db->delete(
                'tplugineinstellungen',
                ['kPlugin', 'cName'],
                [$ins->kPlugin, $ins->cName]
            );
            $this->db->insert('tplugineinstellungen', $ins);
        }
        $this->loadPluginSettings();
        return true;
    }

    public function getSettingName(string $key): string
    {
        return $this->moduleID . '_' . $key;
    }

    public function createApiClient(): ApiClient
    {
        $pluginVersion = $this->plugin->getMeta()->getVersion();
        $client = new ApiClient($this->getSetting('api_key'), $this->getSetting('use_sandbox'), $pluginVersion);
        return $client;
    }

    public function createActionHandler(): ActionHandler
    {
        $apiClient = $this->createApiClient();
        $logger = $this->getLogger();
        return new ActionHandler($this, $apiClient, $logger);
    }

    private function createDataFormatter(Bestellung $order): DataFormatter
    {
        return new DataFormatter($order);
    }

    private function addErrorMessage(string $messageKey, string $key = 'generic'): void
    {
        $message = $this->getTranslation($messageKey);
        Shop::Container()->getAlertService()->addError($message, 'axytos_' . $key, ['saveInSession' => true]);
    }

    private function addSuccessMessage(string $messageKey, string $key = 'generic'): void
    {
        $message = $this->getTranslation($messageKey);
        Shop::Container()->getAlertService()->addSuccess($message, 'axytos_' . $key, ['saveInSession' => true]);
    }

    public function getLogger()
    {
        if (\method_exists($this->plugin, 'getLogger')) {
            $logger = $this->plugin->getLogger();
        } else {
            // fallback for shop versions < 5.3.0
            $logger = Shop::Container()->getLogService();
        }
        return $logger;
    }

    private function addOrderAttribute(Bestellung $order, string $key, string $value): void
    {
        $ins = new stdClass();
        $ins->kBestellung = $order->kBestellung;
        $ins->cName = $key;
        $ins->cValue = $value;
        $this->db->insert('tbestellattribut', $ins);
    }

    private function getOrderAttribute(Bestellung $order, string $key): ?string
    {
        $result = $this->db->select('tbestellattribut', 'kBestellung', (int)$order->kBestellung, 'cName', $key);
        if ($result !== null) {
            return $result->cValue;
        }
        return null;
    }

    private function getTranslation($key): string | null
    {
        $localization = $this->plugin->getLocalization();
        return $localization->getTranslation($key);
    }
}
