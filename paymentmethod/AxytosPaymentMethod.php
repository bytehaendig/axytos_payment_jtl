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

    private function createApiClient(): ApiClient
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

    /** overwrite */
    public function preparePaymentProcess(Bestellung $order): void
    {
        parent::preparePaymentProcess($order);
        $localization = $this->plugin->getLocalization();
        $paymentHash  = $this->generateHash($order);
        $client       = $this->createApiClient();
        $DataFormatter = $this->createDataFormatter($order);
        $precheckData  =  $DataFormatter->createPrecheckData();
    echo json_encode($precheckData, JSON_PRETTY_PRINT);
        try {
            $res = $client->precheck($precheckData);
        } catch (\Exception $e) {
            $this->addErrorMessage($localization->get('error_precheck_failed'));
            return;
        }
if ($this->duringCheckout) {
            Shop::Smarty()->assign('axytosPaymentURL', $this->getNotificationURL($paymentHash) . '&payed');
        }
    }
}
