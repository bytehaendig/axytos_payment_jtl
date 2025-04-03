<?php

namespace Plugin\axytos_payment\paymentmethod;

use JTL\Plugin\Data\PaymentMethod;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\axytos_payment\helpers\ApiClient;
use Plugin\axytos_payment\helpers\DataFormatter;
use stdClass;

class AxytosPaymentMethod extends Method
{
    private PluginInterface $plugin;
    private ?PaymentMethod $method;
    private DbInterface $db;
    private array $settings;

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

    /** overwrite */
    public function getSetting(string $key): mixed
    {
        $setting = parent::getSetting($key);
        if ($setting === null) {
            $setting = $this->settings[$this->getSettingName($key)];
        }
        return $setting;
    }

    public function createApiClient(): ApiClient
    {
        $client = new ApiClient($this->getSetting('api_key'), $this->getSetting('use_sandbox'));
        return $client;
    }

    private function createDataFormatter(Bestellung $order): DataFormatter
    {
        return new DataFormatter($order);
    }

    /** overwrite */
    public function isValidIntern(array $args_arr = []): bool
    {
        return parent::isValidIntern($args_arr) && $this->duringCheckout === 1;
    }

    private function addErrorMessage(string $message, string $key = 'generic'): void
    {
        // $localization = $this->plugin->getLocalization();
        // $errorMessage = $localization->getTranslation($message);
        // if ($errorMessage !== '') {
        //     Shop::Container()->getAlertService()->addError(
        //         $errorMessage,
        //         'axytos_' . $key,
        //     );
        // }
        // TODO: i18n
        Shop::Container()->getAlertService()->addError($message, 'axytos_' . $key, ['saveInSession' => true]);
    }

    private function getLogger()
    {
        if (\method_exists($this->plugin, 'getLogger')) {
            $logger = $this->plugin->getLogger();
        } else {
            // fallback for shop versions < 5.3.0
            $logger = Shop::Container()->getLogService();
        }
        return $logger;
    }

    /** overwrite */
    public function preparePaymentProcess(Bestellung $order): void
    {
        assert($this->duringCheckout === 1);
        parent::preparePaymentProcess($order);
        $client       = $this->createApiClient();
        $dataFormatter = $this->createDataFormatter($order);
        $precheckData  =  $dataFormatter->createPrecheckData();
        $precheckSuccessful = false;
        try {
            $precheckResponse = $client->precheck($precheckData);
            $this->saveInSession('precheck_response', $precheckResponse);
            $precheckSuccessful = true;
        } catch (\Exception $e) {
            $this->getLogger()->error(
                'Axytos payment precheck failed: ' . $e->getMessage(),
                ['order' => $order->kBestellung, 'exception' => $e]
            );
            $this->addErrorMessage('error_precheck_failed', 'precheck_error');
        }
        if ($precheckSuccessful) {
            $responseBody = json_decode($precheckResponse, true);
            $decisionCode = $responseBody['decision'];
            $paymentAccepted = strtolower($decisionCode) === "u";
            if ($paymentAccepted) {
                if ($this->confirmOrder($order, $responseBody)) {
                    $redirectUrl = $this->getNotificationURL($this->generateHash($order));
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } else {
                $this->addErrorMessage('error_payment_rejected', 'precheck_rejected');
            }
        }
        header('Location: ' . Shop::getURL() . '/bestellvorgang.php?editZahlungsart=1');
        exit;
    }

    public function finalizeOrder(Bestellung $order, string $hash, array $args): bool
    {
        // called after redirect to getNotificationURL
        // order is not yet saved to DB
        // true means payment success
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
        // TODO: clarify if Axytos will communicate the payment status
        //
        // $this->addIncomingPayment($order, (object)[
        //     'fBetrag'          => $order->fGesamtsumme,
        //     'fZahlungsgebuehr' => 0,
        //     'cHinweis'         => `Axytos`, // TODO: add transaction ID
        // ]);
        // $this->setOrderStatusToPaid($order);
        $precheckResponse = $this->loadFromSession('precheck_response');
        $this->addOrderAttribute($order, 'axytos_precheck_response', $precheckResponse);
        $this->addOrderAttribute($order, 'axytos_confirmed', '1');
        $this->sendConfirmationMail($order);
    }

    private function confirmOrder(Bestellung $order, array $precheckResponseJson): bool
    {
        $client       = $this->createApiClient();
        $dataFormatter = $this->createDataFormatter($order);
        $confirmData  = $dataFormatter->createConfirmData($precheckResponseJson);
        try {
            $response = $client->orderConfirm($confirmData);
        } catch (\Exception $e) {
            $this->getLogger()->error(
                "Axytos payment order confirmation failed for order {$order->kBestellung}: " . $e->getMessage(),
                ['order' => $order->kBestellung, 'exception' => $e],
            );
            $this->addErrorMessage('error_order_confirmation_failed', 'confirmation_error');
            return false;
        }
        $this->getLogger()->info(
            // TODO: no kBestellung here
            "Axytos payment order confirmation successful for order {$order->kBestellung}.",
            ['order' => $order->kBestellung],
        );
        $responseBody = json_decode($response, true);
        return true;
    }

    private function addOrderAttribute(Bestellung $order, string $key, string $value): void
    {
        $ins = new stdClass();
        $ins->kBestellung = $order->kBestellung;
        $ins->cName = $key;
        $ins->cValue = $value;
        $this->db->insert('tbestellattribut', $ins);
    }

    private function saveInSession(string $key, mixed $value): void
    {
        if (!isset($_SESSION['axytos_payment'])) {
            $_SESSION['axytos_payment'] = [];
        }
        $_SESSION['axytos_payment'][$key] = $value;
    }

    private function loadFromSession(string $key): mixed
    {
        if (isset($_SESSION['axytos_payment']) && array_key_exists($key, $_SESSION['axytos_payment'])) {
            return $_SESSION['axytos_payment'][$key];
        }
        return null;
    }
}
