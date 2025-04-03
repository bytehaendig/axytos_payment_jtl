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
use Plugin\axytos_payment\frontend\AgreementController;

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

        if (Shop::isFrontend()) {
            // Add hook for modifying checkout page to add agreement link
            $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, [$this, 'addAgreementLink']);
            $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args) {
                /** @var Router $router */
                $router = $args['router'];
                $controller = $this->createAgreementController();
                $router->addRoute($controller->getPath(), [$controller, 'getResponse'], 'axytosAgreement');
            });
        } else {
            $dispatcher->listen('backend.notification', [$this, 'checkPayments']);
        }
    }

    private function createAgreementController()
    {
        $controller = new AgreementController($this->getPlugin(), $this->getMethod());
        return $controller;
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

    /**
     * Add agreement link to the payment method in checkout
     *
     * @param array $args
     * @return string
     */
    public function addAgreementLink($ctx)
    {
        $doc = $ctx['document'];
        $smarty = $ctx['smarty'];
        // Only modify output on checkout page
        // TODO: 'Bestellvorgang' should be replaced with a constant or config value to be independent of language
        if (!isset($_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], 'Bestellvorgang') === false) {
            return $doc;
        }

        // Get the payment method ID
        $moduleID = $this->getModuleID();
        // Find the payment method element by its ID
        $paymentMethodElement = $doc->find('#' . $moduleID);
        // If the payment method element is found, append the agreement link
        if ($paymentMethodElement->length > 0) {
            // Find the label element within the payment method
            $noteElement = $paymentMethodElement->find('.checkout-payment-method-note');
            if ($noteElement->length > 0) {
                $controller = $this->createAgreementController();
                $link = $controller->getLink($smarty);
                // Append the agreement link after the label
                $noteElement->after($link);
            }
        }
        // Return the modified HTML
        return $doc->htmlOuter();
    }
}
