<?php
namespace Plugin\AxytosPayment\helpers;

use JTL\Checkout\Bestellung;

/**
 * Helper class for formatting data for Axytos API
 */
class DataFormatter
{
    /**
     * Create basket data for API requests
     *
     * @param Bestellung $order
     * @param string $style order|invoice|refund
     * @return array
     */
    public function createBasketData(Bestellung $order, string $style = "order"): array
    {
        $positions = [];
        $taxGroups = [];
        $grossTotal = $order->fGesamtsumme;
        $netTotal = $grossTotal - $order->fSteuern;
        
        // Process order positions
        foreach ($order->Positionen as $position) {
            $quantity = $position->nAnzahl;
            $netPrice = $position->fPreis;
            $grossPrice = $netPrice * (1 + ($position->fMwSt / 100));
            $taxPercent = $position->fMwSt;
            $lineNetPrice = $netPrice * $quantity;
            $lineGrossPrice = $grossPrice * $quantity;
            $productId = $position->kArtikel;
            
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
                    "netPricePerUnit" => $netPrice,
                    "grossPricePerUnit" => $grossPrice,
                    "netPositionTotal" => $lineNetPrice,
                    "grossPositionTotal" => $lineGrossPrice,
                ];
            } elseif ($style === "refund") {
                $positions[] = [
                    "productId" => $productId,
                    "netRefundTotal" => $lineNetPrice,
                    "grossRefundTotal" => $lineGrossPrice,
                ];
            } else {
                $positions[] = [
                    "productId" => $productId,
                    "productName" => $position->cName,
                    "productCategory" => "General", // TODO: Get actual category
                    "quantity" => $quantity,
                    "taxPercent" => $taxPercent,
                    "netPricePerUnit" => $netPrice,
                    "grossPricePerUnit" => $grossPrice,
                    "netPositionTotal" => $lineNetPrice,
                    "grossPositionTotal" => $lineGrossPrice,
                ];
            }
        }
        
        $result = [
            "netTotal" => $netTotal,
            "grossTotal" => $grossTotal,
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
            $result["currency"] = $order->Waehrung->cISO;
        }
        
        return $result;
    }
    
    /**
     * Create order data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createOrderData(Bestellung $order): array
    {
        $customer = $order->oKunde;
        $customerId = $customer->kKunde ?: $customer->cMail;
        
        return [
            "personalData" => [
                "externalCustomerId" => (string)$customerId,
                "language" => $order->Sprache->cISO,
                "email" => $customer->cMail,
                "mobilePhoneNumber" => $customer->cMobil ?: $customer->cTel,
            ],
            "invoiceAddress" => [
                "company" => $order->oRechnungsadresse->cFirma,
                "firstname" => $order->oRechnungsadresse->cVorname,
                "lastname" => $order->oRechnungsadresse->cNachname,
                "zipCode" => $order->oRechnungsadresse->cPLZ,
                "city" => $order->oRechnungsadresse->cOrt,
                "country" => $order->oRechnungsadresse->cLand,
                "addressLine1" => $order->oRechnungsadresse->cStrasse . ' ' . $order->oRechnungsadresse->cHausnummer,
                "addressLine2" => $order->oRechnungsadresse->cAdressZusatz,
            ],
            "deliveryAddress" => [
                "company" => $order->oLieferadresse->cFirma,
                "firstname" => $order->oLieferadresse->cVorname,
                "lastname" => $order->oLieferadresse->cNachname,
                "zipCode" => $order->oLieferadresse->cPLZ,
                "city" => $order->oLieferadresse->cOrt,
                "country" => $order->oLieferadresse->cLand,
                "addressLine1" => $order->oLieferadresse->cStrasse . ' ' . $order->oLieferadresse->cHausnummer,
                "addressLine2" => $order->oLieferadresse->cAdressZusatz,
            ],
            "basket" => $this->createBasketData($order, "order"),
        ];
    }
    
    /**
     * Create precheck data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createPrecheckData(Bestellung $order): array
    {
        $orderData = $this->createOrderData($order);
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
    public function createConfirmData(Bestellung $order): array
    {
        $orderData = $this->createOrderData($order);
        $precheckResponse = json_decode($order->getBestellungMeta('precheck_response'), true);
        
        $confirmData = [
            "externalOrderId" => $order->cBestellNr,
            "date" => date('c'),
            "orderPrecheckResponse" => $precheckResponse
        ];
        
        return array_merge($orderData, $confirmData);
    }
    
    /**
     * Create invoice data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createInvoiceData(Bestellung $order): array
    {
        return [
            "externalOrderId" => $order->cBestellNr,
            "externalInvoiceNumber" => $order->cBestellNr,
            "externalInvoiceDisplayName" => sprintf("Invoice #%s", $order->cBestellNr),
            "externalSubOrderId" => "",
            "date" => date('c', strtotime($order->dErstellt)),
            "dueDateOffsetDays" => 14,
            "basket" => $this->createBasketData($order, "invoice"),
        ];
    }
    
    /**
     * Create shipping data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createShippingData(Bestellung $order): array
    {
        $positions = [];
        
        foreach ($order->Positionen as $position) {
            if ($position->kArtikel > 0) {
                $positions[] = [
                    "productId" => $position->kArtikel,
                    "quantity" => $position->nAnzahl,
                ];
            }
        }
        
        return [
            "externalOrderId" => $order->cBestellNr,
            "externalSubOrderId" => "",
            "basketPositions" => $positions,
            "shippingDate" => date('c'),
        ];
    }
    
    /**
     * Create refund data for API requests
     *
     * @param Bestellung $order
     * @return array
     */
    public function createRefundData(Bestellung $order): array
    {
        $invoiceNumber = $order->getBestellungMeta('axytos_invoice_number');
        
        return [
            "externalOrderId" => $order->cBestellNr,
            "refundDate" => date('c'),
            "originalInvoiceNumber" => $invoiceNumber,
            "externalSubOrderId" => "",
            "basket" => $this->createBasketData($order, "refund"),
        ];
    }
}
