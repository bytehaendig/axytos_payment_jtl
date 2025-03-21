<?php

namespace Plugin\axytos_payment\paymentmethod;

use JTL\Plugin\Data\PaymentMethod;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\PluginInterface;
use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\axytos_payment\helpers\ApiClient;

class AxytosPaymentMethod extends Method
{
    /** @var PluginInterface */
    private PluginInterface $plugin;

    /** @var PaymentMethod|null */
    private ?PaymentMethod $method;

    /**
     * @inheritDoc
     */
    public function init(int $nAgainCheckout = 0): self
    {
        parent::init($nAgainCheckout);

        $pluginID     = PluginHelper::getIDByModuleID($this->moduleID);
        $this->plugin = PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID);
        $this->method = $this->plugin->getPaymentMethods()->getMethodByID($this->moduleID);

        return $this;
    }

    /**
     * @return PaymentMethod
     */
    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function getSetting(string $key): mixed
    {
        $setting = parent::getSetting($key);

        if ($setting === null) {
            $setting = $this->plugin->getConfig()->getValue($this->getMethod()->getModuleID() . '_' . $key);
        }

        if ($key === 'api_key') {
            $setting = 'SECRET';
        }

        return $setting;
    }

    /**
    * @return ApiClient
    */
    private function createApiClient(): ApiClient
    {
        $client = new ApiClient($this->getSetting('api_key'), $this->getSetting('use_sandbox'));
        return $client;
    }

    /**
    * @inheritDoc
    */
    public function isValidIntern(array $args_arr = []): bool
    {
        return parent::isValidIntern($args_arr) && $this->duringCheckout === 1;
    }

    /**
    * @inheritDoc
    */
    public function preparePaymentProcess(Bestellung $order): void
    {
        parent::preparePaymentProcess($order);

        $localization = $this->plugin->getLocalization();
        $paymentHash  = $this->generateHash($order);
        if ($this->duringCheckout) {
            Shop::Smarty()->assign('axytosPaymentURL', $this->getNotificationURL($paymentHash) . '&payed');
        }
    }
}
