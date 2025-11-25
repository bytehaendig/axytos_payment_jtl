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

function getProductCategory($pos): string
{
    $posType = $pos->nPosTyp;
    return match ($posType) {
        C_WARENKORBPOS_TYP_ARTIKEL                  => 'Article',
        C_WARENKORBPOS_TYP_VERSANDPOS               => 'Shipping',
        C_WARENKORBPOS_TYP_KUPON                    => 'Coupon',
        C_WARENKORBPOS_TYP_GUTSCHEIN                => 'Voucher',
        C_WARENKORBPOS_TYP_ZAHLUNGSART              => 'Payment',
        C_WARENKORBPOS_TYP_VERSANDZUSCHLAG          => 'Shipping surcharge',
        C_WARENKORBPOS_TYP_NEUKUNDENKUPON           => 'New customer coupon',
        C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR         => 'Cash on delivery fee',
        C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG => 'Shipping dependent on article',
        C_WARENKORBPOS_TYP_VERPACKUNG               => 'Packaging',
        C_WARENKORBPOS_TYP_GRATISGESCHENK           => 'Promotion',
        C_WARENKORBPOS_TYP_ZINSAUFSCHLAG       => 'Interest surcharge',
        C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR => 'Processing fee',
        default => 'Unspecified',
    };
}

function html_decode_recursive($data) {
    if (is_string($data)) {
        return html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            // Also decode keys if they're strings
            $decoded_key = is_string($key) ?
                html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $key;
            $result[$decoded_key] = html_decode_recursive($value);
        }
        return $result;
    } elseif (is_object($data)) {
        // Create deep copy using serialization/unserialization
        $clone = unserialize(serialize($data));
        foreach (get_object_vars($clone) as $key => $value) {
            $clone->$key = html_decode_recursive($value);
        }
        return $clone;
    }
    // For primitives (int, float, bool, null), return as-is (they're copied by value)
    return $data;
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
        // decode HTML entities in order data
        // this is needed because JTL sometimes gives the order data with HTML entities encoded
        $decodedOrder = html_decode_recursive($order);
        $this->order = $decodedOrder;
        // helper
        $this->orderHelper = new Order($decodedOrder);
    }

    public function getExternalOrderId(): string
    {
        return $this->order->cBestellNr;
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
                    "productCategory" => getProductCategory($position),
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
    public function createOrderData(): array
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
    public function createPrecheckData(array $orderData): array
    {
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
    public function createConfirmData(array $precheckResponseJson, array $orderData): array
    {
        $confirmData = [
            "externalOrderId" => $this->getExternalOrderId(),
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
            "externalOrderId" => $this->getExternalOrderId(),
            "externalInvoiceNumber" => $this->order->cBestellNr,
            "externalInvoiceDisplayName" => sprintf("Bestellung %s", $this->order->cBestellNr),
            "externalSubOrderId" => "",
            "date" => date('c'),
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
            "externalOrderId" => $this->getExternalOrderId(),
            "externalSubOrderId" => "",
            "basketPositions" => $positions,
            "shippingDate" => $this->order->dVersandDatum ? date('c', strtotime($this->order->dVersandDatum)) : date('c'),
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
