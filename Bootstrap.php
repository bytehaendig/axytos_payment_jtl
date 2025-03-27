<?php

declare(strict_types=1);

namespace Plugin\axytos_payment;

use JTL\Backend\Notification;
use JTL\Backend\NotificationEntry;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Payment\Method;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\adminmenu\Handler;

/**
 * Class Bootstrap
 * @package Plugin\jtl_samplepayment
 */
class Bootstrap extends Bootstrapper
{
    private Method $method;
    /**
     * @inheritDoc
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $this->method = Method::create($this->getModuleID());
        $this->method = $this->getMethod();
        parent::boot($dispatcher);
        if (!Shop::isFrontend()) {
            $dispatcher->listen('backend.notification', [$this, 'checkPayments']);
        }
        $dispatcher->listen('shop.hook.' . \HOOK_PLUGIN_SAVE_OPTIONS, [$this, 'handlePluginSettingsSave']);
    }

    private function getModuleID(): string
    {
        $methods = $this->getPlugin()->getPaymentMethods()->getMethods();
        $moduleId = $methods[0]->getModuleID();
        return $moduleId;
    }

    public function getMethod(): AxytosPaymentMethod
    {
        return $this->method;
    }

    /**
     * @return void
     */
    public function checkPayments(): void
    {
        /** @var PaymentMethod $paymentMethod */
        $method = $this->getMethod();
        if ($method instanceof AxytosPaymentMethod && $method->duringCheckout !== 1) {
            $note = new NotificationEntry(
                NotificationEntry::TYPE_WARNING,
                $paymentMethod->getName(),
                'Die Zahlungsart kann nur mit Zahlung vor Bestellabschluss verwendet werden',
                Shop::getAdminURL() . '/paymentmethods?kZahlungsart=' . $method->kZahlungsart
                    . '&token=' . $_SESSION['jtl_token']
            );
            $note->setPluginId($this->getPlugin()->getPluginID());
            Notification::getInstance()->addNotify($note);
        }
    }

    /**
     * Handle plugin settings save to encrypt API key
     * @param array $args
     */
    public function handlePluginSettingsSave(array $args): void
    {
        die($args);
        // Check if these are our plugin's settings being saved
        if (isset($args['kPlugin']) && (int)$args['kPlugin'] === $this->getPlugin()->getID()) {
            // Check if the API key is being set
            if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
                // Get the encryption service
                $encryption = Shop::Container()->getCryptoService();

                // Encrypt the API key
                $_POST['api_key'] = $encryption->encryptXTEA($_POST['api_key']);
            }
        }
    }

    /**
     * Called when the admin loads the plugin settings page
     * This is part of the BootstrapperInterface
     */
    public function loaded(): int
    {
        $result = parent::loaded();
        // Only execute in admin context
        if (Shop::isAdmin()) {
            // Get the plugin configuration
            $config = $this->getPlugin()->getConfig();
            $apiKey = $config->getValue($this->getModuleID() . '_api_key');

            // Decrypt the API key for display in admin
            if (!empty($apiKey)) {
                try {
                    // Get the encryption service
                    $encryption = Shop::Container()->getCryptoService();

                    // Decrypt the API key
                    $decryptedApiKey = $encryption->decryptXTEA($apiKey);

                    // Update the config with the decrypted value for display
                    $options = $config->getOptions();
                    $options['api_key'] = $decryptedApiKey;
                    $config->setOptions($options);
                } catch (\Exception $e) {
                    // Handle decryption error - possibly not encrypted yet
                }
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $handler = new Handler($this->getPlugin(), $this->getMethod());
        return $handler->render($tabName, $menuID, $smarty);
    }
}
