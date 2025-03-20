<?php

declare(strict_types=1);

namespace Bytehaendig\axytos_payment;

use JTL\Backend\Notification;
use JTL\Backend\NotificationEntry;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Payment\Method;
use JTL\Shop;
use Bytehaendig\axytos_payment\paymentmethod\AxytosPaymentMethod;

/**
 * Class Bootstrap
 * @package Plugin\jtl_samplepayment
 */
class Bootstrap extends Bootstrapper
{
    /**
     * @inheritDoc
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        if (!Shop::isFrontend()) {
            $dispatcher->listen('backend.notification', [$this, 'checkPayments']);
        }
    }

    /**
     * @return void
     */
    public function checkPayments(): void
    {
        /** @var PaymentMethod $paymentMethod */
        foreach ($this->getPlugin()->getPaymentMethods()->getMethods() as $paymentMethod) {
            $method = Method::create($paymentMethod->getModuleID());
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
    }
}
