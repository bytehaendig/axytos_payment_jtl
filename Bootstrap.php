<?php

declare(strict_types=1);

namespace Plugin\axytos_payment;

use JTL\Backend\Notification;
use JTL\Backend\NotificationEntry;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Payment\Method;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\axytos_payment\helpers\CronHelper;
use Plugin\axytos_payment\helpers\Utils;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\adminmenu\SetupHandler;
use Plugin\axytos_payment\adminmenu\StatusHandler;
use Plugin\axytos_payment\adminmenu\DevHandler;
use Plugin\axytos_payment\adminmenu\InvoicesHandler;
use Plugin\axytos_payment\frontend\AgreementController;
use Plugin\axytos_payment\frontend\ApiInvoiceIdsController;

/**
 * Class Bootstrap
 * @package Plugin\jtl_samplepayment
 */
class Bootstrap extends Bootstrapper
{
    private ?Method $method = null;
    private bool $cronHooksRegistered = false;
    /**
     * @inheritDoc
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        if (Shop::isFrontend()) {
            // Add hook for modifying checkout page to add agreement link
            $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, [$this, 'addAgreementLink']);
            $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args) {
                /** @var Router $router */
                $router = $args['router'];
                $agreementController = $this->createAgreementController();
                $router->addRoute($agreementController->getPath(), [$agreementController, 'getResponse'], 'axytosAgreement');

                // Register update-invoice-ids endpoint
                $updateController = $this->createApiInvoiceIdsController();
                $router->addRoute(
                    $updateController->getPath(),
                    [$updateController, 'getResponse'],
                    'axytosApiInvoiceIds',
                    ['POST']
                );
            });
        } else {
            $dispatcher->listen('backend.notification', [$this, 'checkPayments']);
        }
        $dispatcher->hookInto(\HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [$this, 'onUpdateOrderStatus']);
        $this->setupCronHooks($dispatcher);
    }

    private function createAgreementController()
    {
        $controller = new AgreementController($this->getPlugin(), $this->getMethod(), Shop::Container()->getCache());
        return $controller;
    }

    private function createApiInvoiceIdsController()
    {
        $controller = new ApiInvoiceIdsController($this->getPlugin(), $this->getMethod());
        return $controller;
    }

    public function getModuleID(): string
    {
        $methods = $this->getPlugin()->getPaymentMethods()->getMethods();
        $moduleId = $methods[0]->getModuleID();
        return $moduleId;
    }

    private function getPaymentMethodId(): ?int
    {
        return Utils::getPaymentMethodId($this->getDB());
    }

    public function getMethod(): ?AxytosPaymentMethod
    {
        if ($this->method === null) {
            $this->method = Utils::createPaymentMethod($this->getDB());
        }
        return $this->method;
    }

    /**
     * @return void
     */
    public function checkPayments(): void
    {
        /** @var PaymentMethod $paymentMethod */
        $method = $this->getMethod();
        if ($method && $method->duringCheckout !== 1) {
            $paymentMethodId = $this->getPaymentMethodId();
            $note = new NotificationEntry(
                NotificationEntry::TYPE_WARNING,
                $method->getName(),
                __('This payment method can only be used with payment before order completion'),
                Shop::getAdminURL() . '/paymentmethods?kZahlungsart=' . $paymentMethodId
                    . '&token=' . $_SESSION['jtl_token']
            );
            $note->setPluginId($this->getPlugin()->getPluginID());
            Notification::getInstance()->addNotify($note);
        }
    }

    public function onUpdateOrderStatus(array $args): void
    {
        $status = $args['status'];
        $order = $args['oBestellung'];
        if ($status === \BESTELLUNG_STATUS_VERSANDT) {
            if (Utils::isAxytosOrder($this->getDB(), $order)) {
                $paymentMethod = $this->getMethod();
                if ($paymentMethod) {
                    $paymentMethod->orderWasShipped($order->kBestellung);
                }
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

    private function setupCronHooks(Dispatcher $dispatcher = null, CronHelper $cronHelper = null)
    {
        if ($this->cronHooksRegistered) {
            return;
        }
        
        $dispatcher = $dispatcher ?: Dispatcher::getInstance();
        $cronHelper = $cronHelper ?: new CronHelper();
        $dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, [$cronHelper, 'availableCronjobType']);
        $dispatcher->listen(Event::MAP_CRONJOB_TYPE, [$cronHelper, 'mappingCronjobType']);
        
        $this->cronHooksRegistered = true;
    }

    /**
     * @inheritdoc
     */
    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);
        $cronHelper = new CronHelper();
        $this->setupCronHooks(null, $cronHelper);
        $cronHelper->installCronIfMissing();
        // don't run this migration because it exceeds PHP request limits
        // if ($newVersion === '0.9.3') {
        //     $versionMigrator = new VersionMigrator($this);
        //     $versionMigrator->rerun_failed_cancallations()
        // }
    }

    public function enabled(): void
    {
        parent::enabled();
        $cronHelper = new CronHelper();
        $this->setupCronHooks(null, $cronHelper);
        $cronHelper->installCronIfMissing();
    }

    public function disabled(): void
    {
        parent::disabled();
        $cronHelper = new CronHelper();
        $cronHelper->uninstallCron();
    }

    public function installed(): void
    {
        parent::installed();
        $cronHelper = new CronHelper();
        $this->setupCronHooks(null, $cronHelper);
        $cronHelper->installCronIfMissing();
    }

    public function uninstalled(bool $deleteData = true): void
    {
        parent::uninstalled($deleteData);
        $cronHelper = new CronHelper();
        $cronHelper->uninstallCron();
    }

    /**
     * @inheritdoc
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        // Setup gettext and Smarty plugins for all admin tabs
        $this->setupSmartyForAdmin($smarty);
        if ($tabName == "API Setup") {
            $handler = new SetupHandler($this->getPlugin(), $this->getMethod());
            return $handler->render($tabName, $menuID, $smarty);
        }
        if ($tabName == "Status") {
            $handler = new StatusHandler($this->getPlugin(), $this->getMethod(), $this->getDB());
            return $handler->render($tabName, $menuID, $smarty);
        }
        if ($tabName == "Invoices") {
            $handler = new InvoicesHandler($this->getPlugin(), $this->getMethod(), $this->getDB());
            return $handler->render($tabName, $menuID, $smarty);
        }
        // Only show Development tab in development mode
        if ($tabName == "Development" && $this->isDevMode()) {
            $handler = new DevHandler($this->getPlugin(), $this->getMethod(), $this->getDB());
            return $handler->render($tabName, $menuID, $smarty);
        }
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

    /**
     * Setup gettext and Smarty plugins for admin interface
     * 
     * @param JTLSmarty $smarty
     * @return void
     */
    private function setupSmartyForAdmin(JTLSmarty $smarty): void
    {
        // Setup gettext for template translations
        $localePath = $this->getPlugin()->getPaths()->getBasePath() . 'locale';
        bindtextdomain('axytos_payment', $localePath);
        textdomain('axytos_payment');

        // Register Smarty plugins (use try-catch to handle already registered plugins)
        try {
            $smarty->registerPlugin('modifier', '__', function($string) {
                return __($string);
            });
        } catch (\Exception $e) {
            // Plugin already registered, ignore
        }
        
        try {
            $smarty->registerPlugin('modifier', 'sprintf', 'sprintf');
        } catch (\Exception $e) {
            // Plugin already registered, ignore
        }
        
        try {
            $smarty->registerPlugin('modifier', 'germanDate', function($timestamp, $includeTime = true, $includeSeconds = false) {
                if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
                    return '-';
                }
                
                // Convert to Unix timestamp if it's a datetime string
                if (!is_numeric($timestamp)) {
                    $timestamp = strtotime($timestamp);
                }
                
                if ($includeSeconds) {
                    return date('d. M Y H:i:s', $timestamp);
                } elseif ($includeTime) {
                    return date('d. M Y H:i', $timestamp);
                } else {
                    return date('d. M Y', $timestamp);
                }
            });
        } catch (\Exception $e) {
            // Plugin already registered, ignore
        }
    }

    /**
     * Check if development mode is enabled
     * 
     * @return bool
     */
    private function isDevMode(): bool
    {
        return (defined('PLUGIN_DEV_MODE') && PLUGIN_DEV_MODE) ||
               (defined('SHOW_DEBUG_BAR') && SHOW_DEBUG_BAR);
    }
}
