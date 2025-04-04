<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Checkout\Bestellung;
use JTL\Helpers\Order;
use JTL\Session\Frontend;

function getAddress($addr)
{
    $address = [
        "company" => $addr->cFirma ?? '',
        "firstname" => $addr->cVorname ?? '',
        "lastname" => $addr->cNachname ?? '',
        "zipCode" => $addr->cPLZ,
        "city" => $addr->cOrt,
        "country" => $addr->cLand,
        "addressLine1" => $addr->cStrasse . ' ' . $addr->cHausnummer,
        "addressLine2" => $addr->cAdressZusatz ?? '',
    ];
    return $address;
}

function getProductId($pos): string
{
    // TODO: better productId (especially relevant for shipping)
    return $pos->cArtNr ?: "-";
}

/**
 * Helper class for formatting data for Axytos API
 */
class DataFormatter
{
    private Bestellung $order;
    private Order $orderHelper;

    public function __construct(Bestellung $order)
    {
        $this->order = $order;
        // helper
        $this->orderHelper = new Order($order);
    }
    /**
     * Create basket data for API requests
     *
     * @param string $style order|invoice|refund
     * @return array
     */
    private function createBasketData(string $style = "order"): array
    {
        $positions = [];
        $taxGroups = [];
        $grossTotal = $this->order->fGesamtsumme;
        $netTotal = 0; // work-around - for some reason order->fGesamtsummeNette is 0
        // Process order positions
        foreach ($this->orderHelper->getPositions() as $position) {
            $quantity = $position->nAnzahl;
            $netPrice = $position->fPreis;
            $grossPrice = $netPrice * (1 + ($position->fMwSt / 100));
            $taxPercent = (float)$position->fMwSt;
            $lineNetPrice = $netPrice * $quantity;
            $lineGrossPrice = $grossPrice * $quantity;
            $productId = getProductId($position);
            $netPriceDisplay = round($netPrice, 2);
            $grossPriceDisplay = round($grossPrice, 2);
            $lineNetPriceDisplay = round($lineNetPrice, 2);
            $lineGrossPriceDisplay = round($lineGrossPrice, 2);
            $netTotal += $lineNetPrice;
            // Track tax groups
            if (!isset($taxGroups[$taxPercent])) {
                $taxGroups[$taxPercent] = [
                    'tax' => 0,
                    'value' => 0
                ];
            }
            $taxGroups[$taxPercent]['tax'] += ($lineGrossPrice - $lineNetPrice);
            $taxGroups[$taxPercent]['value'] += $lineNetPrice;
            // Format position data based on style
            if ($style === "invoice") {
                $positions[] = [
                    "productId" => $productId,
                    "quantity" => $quantity,
                    "taxPercent" => $taxPercent,
                    "netPricePerUnit" => $netPriceDisplay,
                    "grossPricePerUnit" => $grossPriceDisplay,
                    "netPositionTotal" => $lineNetPriceDisplay,
                    "grossPositionTotal" => $lineGrossPriceDisplay,
                ];
            } elseif ($style === "refund") {
                $positions[] = [
                    "productId" => $productId,
                    "netRefundTotal" => $lineNetPriceDisplay,
                    "grossRefundTotal" => $lineGrossPriceDisplay,
                ];
            } else {
                $positions[] = [
                    "productId" => $productId,
                    "productName" => $position->cName,
                    "productCategory" => "General", // TODO: Get actual category
                    "quantity" => $quantity,
                    "taxPercent" => $taxPercent,
                    "netPricePerUnit" => $netPriceDisplay,
                    "grossPricePerUnit" => $grossPriceDisplay,
                    "netPositionTotal" => $lineNetPriceDisplay,
                    "grossPositionTotal" => $lineGrossPriceDisplay,
                ];
            }
        }
        $result = [
            "netTotal" => round($netTotal, 2),
            "grossTotal" => round($grossTotal, 2),
            "positions" => $positions,
        ];
        // Add tax groups for invoice and refund styles
        if ($style === "invoice" || $style === "refund") {
            $formattedTaxGroups = [];
            foreach ($taxGroups as $taxPercent => $values) {
                $formattedTaxGroups[] = [
                    "taxPercent" => $taxPercent,
                    "valueToTax" => round($values['value'], 2),
                    "total" => round($values['tax'], 2),
                ];
            }
            $result["taxGroups"] = $formattedTaxGroups;
        }
        // Add currency for order style
        if ($style === "order") {
            $result["currency"] = $this->orderHelper->getCurrency()->getCode();
        }
        return $result;
    }

    /**
     * Create order data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    private function createOrderData(): array
    {
        $customer = $this->orderHelper->getCustomer() ?: Frontend::getCustomer();
        $customerId = $customer->cMail; // always use email as customer ID
        return [
            "personalData" => [
                "externalCustomerId" => (string)$customerId,
                "language" => Frontend::getInstance()->getLanguage()->cISOSprache,
                "email" => $customer->cMail,
                "mobilePhoneNumber" => $customer->cMobil ? $customer->cMobil : $customer->cTel ?? '',
            ],
            "invoiceAddress" => getAddress($this->order->oRechnungsadresse),
            "deliveryAddress" => getAddress($this->order->oLieferadresse ?: $this->order->oRechnungsadresse),
            "basket" => $this->createBasketData("order"),
        ];
    }

    /**
     * Create precheck data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createPrecheckData(): array
    {
        $orderData = $this->createOrderData($this->order);
        $precheckData = [
            "requestMode" => "SingleStep",
            "paymentTypeSecurity" => "U",
            "selectedPaymentType" => "",
            "proofOfInterest" => "AAE",
        ];
        return array_merge($orderData, $precheckData);
    }
    /**
     * Create confirm data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createConfirmData(array $precheckResponseJson): array
    {
        $orderData = $this->createOrderData($this->order);
        $confirmData = [
            "externalOrderId" => $this->order->cBestellNr,
            "date" => date('c'),
            "orderPrecheckResponse" => $precheckResponseJson,
        ];
        return array_merge($orderData, $confirmData);
    }

    /**
     * Create invoice data for API requests
     *
     * @return array
     */
    public function createInvoiceData(): array
    {
        return [
            "externalOrderId" => $this->order->cBestellNr,
            // "externalInvoiceNumber" => $this->order->cBestellNr,
            // "externalInvoiceDisplayName" => sprintf("Invoice #%s", $this->order->cBestellNr),
            "externalSubOrderId" => "",
            "date" => date('c', strtotime($this->order->dErstellt)),
            // TODO: should this be configurable?
            "dueDateOffsetDays" => 14,
            "basket" => $this->createBasketData("invoice"),
        ];
    }

    /**
     * Create shipping data for API requests
     *
     * @return array
     */
    public function createShippingData(): array
    {
        $positions = [];
        foreach ($this->order->Positionen as $position) {
            if ($position->kArtikel > 0) {
                $positions[] = [
                    "productId" => getProductId($position),
                    "quantity" => $position->nAnzahl,
                ];
            }
        }
        return [
            "externalOrderId" => $this->order->cBestellNr,
            "externalSubOrderId" => "",
            "basketPositions" => $positions,
            "shippingDate" => date('c'),
        ];
    }

    /**
     * Create refund data for API requests
     *
     * @return array
     */
    // public function createRefundData(): array
    // {
    //     $invoiceNumber = $this->order->getBestellungMeta('axytos_invoice_number');
    //     return [
    //         "externalOrderId" => $this->order->cBestellNr,
    //         "refundDate" => date('c'),
    //         "originalInvoiceNumber" => $invoiceNumber,
    //         "externalSubOrderId" => "",
    //         "basket" => $this->createBasketData("refund"),
    //     ];
    // }
}
