<?php declare(strict_types=1);

namespace Tests\Unit;

use JTL\Catalog\Currency;
use JTL\Checkout\Bestellung;
use JTL\Helpers\Order;
use JTL\Session\Frontend;
use Tests\BaseTestCase;
use Plugin\axytos_payment\helpers\DataFormatter;

// Define JTL constants for testing
if (!defined('C_WARENKORBPOS_TYP_ARTIKEL')) {
    define('C_WARENKORBPOS_TYP_ARTIKEL', 1);
    define('C_WARENKORBPOS_TYP_VERSANDPOS', 2);
    define('C_WARENKORBPOS_TYP_KUPON', 3);
    define('C_WARENKORBPOS_TYP_GUTSCHEIN', 4);
    define('C_WARENKORBPOS_TYP_ZAHLUNGSART', 5);
    define('C_WARENKORBPOS_TYP_VERSANDZUSCHLAG', 6);
    define('C_WARENKORBPOS_TYP_NEUKUNDENKUPON', 7);
    define('C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR', 8);
    define('C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG', 9);
    define('C_WARENKORBPOS_TYP_VERPACKUNG', 10);
    define('C_WARENKORBPOS_TYP_GRATISGESCHENK', 11);
    define('C_WARENKORBPOS_TYP_ZINSAUFSCHLAG', 12);
    define('C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR', 13);
}

final class DataFormatterTest extends BaseTestCase
{
    private function createBasicOrder(): object
    {
        return (object) [
            'cBestellNr' => 'ORDER123',
            'fGesamtsumme' => 100.50,
            'dErstellt' => '2023-01-01 12:00:00',
            'dVersandDatum' => '2023-01-02 10:00:00',
            'kSprache' => 1,
            'Positionen' => [],
            'oRechnungsadresse' => $this->createBasicAddress(),
            'oLieferadresse' => null,
            'oKunde' => null,
            'Waehrung' => null
        ];
    }

    private function createBasicAddress(): object
    {
        return (object) [
            'cFirma' => '',
            'cVorname' => 'John',
            'cNachname' => 'Doe',
            'cPLZ' => '12345',
            'cOrt' => 'Test City',
            'cLand' => 'DE',
            'cStrasse' => 'Test Street',
            'cHausnummer' => '123',
            'cAdressZusatz' => ''
        ];
    }

    private function createMockCustomer(): \JTL\Customer\Customer
    {
        $mockCustomer = $this->createMock(\JTL\Customer\Customer::class);
        $mockCustomer->cMail = 'test@example.com';
        $mockCustomer->cMobil = '123456789';
        $mockCustomer->cTel = '987654321';
        $mockCustomer->kKundengruppe = 1;
        return $mockCustomer;
    }

    private function createMockCurrency(): Currency
    {
        $mockCurrency = $this->createMock(Currency::class);
        $mockCurrency->method('getCode')->willReturn('EUR');
        return $mockCurrency;
    }

    private function createMockOrderHelper(?object $customer = null, ?Currency $currency = null): Order
    {
        $mockOrderHelper = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockOrderHelper->method('getCustomer')->willReturn($customer ?? $this->createMockCustomer());
        $mockOrderHelper->method('getLanguage')->willReturn('de');
        $mockOrderHelper->method('getCurrency')->willReturn($currency ?? $this->createMockCurrency());
        $mockOrderHelper->method('getPositions')->willReturn([]);

        return $mockOrderHelper;
    }

    private function createDataFormatter(?object $order = null, ?Order $orderHelper = null): DataFormatter
    {
        $order = $order ?? $this->createBasicOrder();
        $orderHelper = $orderHelper ?? $this->createMockOrderHelper();

        return new DataFormatter($order, $orderHelper);
    }

    public function testGetExternalOrderId(): void
    {
        // Arrange
        $order = $this->createBasicOrder();
        $dataFormatter = $this->createDataFormatter($order);

        // Act
        $result = $dataFormatter->getExternalOrderId();

        // Assert
        $this->assertEquals('ORDER123', $result);
    }

    public function testGetAddressWithCompleteData(): void
    {
        // Arrange
        $address = (object) [
            'cFirma' => 'Test Company',
            'cVorname' => 'John',
            'cNachname' => 'Doe',
            'cPLZ' => '12345',
            'cOrt' => 'Test City',
            'cLand' => 'DE',
            'cStrasse' => 'Test Street',
            'cHausnummer' => '123',
            'cAdressZusatz' => 'Floor 5'
        ];

        // Act
        $result = \Plugin\axytos_payment\helpers\getAddress($address);

        // Assert
        $expected = [
            'company' => 'Test Company',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'zipCode' => '12345',
            'city' => 'Test City',
            'country' => 'DE',
            'addressLine1' => 'Test Street 123',
            'addressLine2' => 'Floor 5'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetAddressWithMinimalData(): void
    {
        // Arrange
        $address = (object) [
            'cPLZ' => '54321',
            'cOrt' => 'Another City',
            'cLand' => 'US',
            'cStrasse' => 'Another Street',
            'cHausnummer' => '456'
        ];

        // Act
        $result = \Plugin\axytos_payment\helpers\getAddress($address);

        // Assert
        $expected = [
            'company' => '',
            'firstname' => '',
            'lastname' => '',
            'zipCode' => '54321',
            'city' => 'Another City',
            'country' => 'US',
            'addressLine1' => 'Another Street 456',
            'addressLine2' => ''
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetProductIdWithValidArticleNumber(): void
    {
        // Arrange
        $position = (object) ['cArtNr' => 'ART123'];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductId($position);

        // Assert
        $this->assertEquals('ART123', $result);
    }

    public function testGetProductIdWithEmptyArticleNumber(): void
    {
        // Arrange
        $position = (object) ['cArtNr' => ''];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductId($position);

        // Assert
        $this->assertEquals('-', $result);
    }

    public function testGetProductIdWithNullArticleNumber(): void
    {
        // Arrange
        $position = (object) ['cArtNr' => null];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductId($position);

        // Assert
        $this->assertEquals('-', $result);
    }

    public function testGetProductCategoryArtikel(): void
    {
        // Arrange
        $position = (object) ['nPosTyp' => C_WARENKORBPOS_TYP_ARTIKEL];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductCategory($position);

        // Assert
        $this->assertEquals('Article', $result);
    }

    public function testGetProductCategoryVersand(): void
    {
        // Arrange
        $position = (object) ['nPosTyp' => C_WARENKORBPOS_TYP_VERSANDPOS];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductCategory($position);

        // Assert
        $this->assertEquals('Shipping', $result);
    }

    public function testGetProductCategoryKupon(): void
    {
        // Arrange
        $position = (object) ['nPosTyp' => C_WARENKORBPOS_TYP_KUPON];

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductCategory($position);

        // Assert
        $this->assertEquals('Coupon', $result);
    }

    public function testGetProductCategoryDefault(): void
    {
        // Arrange
        $position = (object) ['nPosTyp' => 999]; // Unknown type

        // Act
        $result = \Plugin\axytos_payment\helpers\getProductCategory($position);

        // Assert
        $this->assertEquals('Unspecified', $result);
    }

    public function testHtmlDecodeRecursiveWithString(): void
    {
        // Arrange
        $input = 'Test &amp; string &lt;with&gt; entities';

        // Act
        $result = \Plugin\axytos_payment\helpers\html_decode_recursive($input);

        // Assert
        $this->assertEquals('Test & string <with> entities', $result);
    }

    public function testHtmlDecodeRecursiveWithArray(): void
    {
        // Arrange
        $input = [
            'name' => 'Test &amp; Name',
            'description' => 'Desc &lt;with&gt; tags',
            'nested' => [
                'value' => 'Nested &quot;value&quot;'
            ]
        ];

        // Act
        $result = \Plugin\axytos_payment\helpers\html_decode_recursive($input);

        // Assert
        $expected = [
            'name' => 'Test & Name',
            'description' => 'Desc <with> tags',
            'nested' => [
                'value' => 'Nested "value"'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testHtmlDecodeRecursiveWithObject(): void
    {
        // Arrange
        $input = (object) [
            'title' => 'Object &amp; Title',
            'content' => 'Content &lt;here&gt;'
        ];

        // Act
        $result = \Plugin\axytos_payment\helpers\html_decode_recursive($input);

        // Assert
        $this->assertEquals('Object & Title', $result->title);
        $this->assertEquals('Content <here>', $result->content);
    }

    public function testHtmlDecodeRecursiveWithPrimitives(): void
    {
        // Arrange & Act & Assert - Testing primitive values that should pass through unchanged
        $this->assertEquals(123, \Plugin\axytos_payment\helpers\html_decode_recursive(123));
        $this->assertEquals(45.67, \Plugin\axytos_payment\helpers\html_decode_recursive(45.67));
        $this->assertTrue(\Plugin\axytos_payment\helpers\html_decode_recursive(true));
        $this->assertFalse(\Plugin\axytos_payment\helpers\html_decode_recursive(false));
        $this->assertNull(\Plugin\axytos_payment\helpers\html_decode_recursive(null));
    }

    public function testCreateOrderData(): void
    {
        // Arrange
        $order = $this->createBasicOrder();
        $customer = $this->createMockCustomer();
        $order->oKunde = $customer;
        $order->oLieferadresse = $order->oRechnungsadresse;
        $orderHelper = $this->createMockOrderHelper($customer);
        $dataFormatter = $this->createDataFormatter($order, $orderHelper);

        // Act
        $result = $dataFormatter->createOrderData();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('personalData', $result);
        $this->assertArrayHasKey('invoiceAddress', $result);
        $this->assertArrayHasKey('deliveryAddress', $result);
        $this->assertArrayHasKey('basket', $result);

        // Check personal data
        $this->assertEquals('test@example.com', $result['personalData']['externalCustomerId']);
        $this->assertEquals('de', $result['personalData']['language']);
        $this->assertEquals('test@example.com', $result['personalData']['email']);
        $this->assertEquals('123456789', $result['personalData']['mobilePhoneNumber']);

        // Check invoice address structure
        $this->assertEquals('John', $result['invoiceAddress']['firstname']);
        $this->assertEquals('Doe', $result['invoiceAddress']['lastname']);
        $this->assertEquals('12345', $result['invoiceAddress']['zipCode']);
        $this->assertEquals('Test City', $result['invoiceAddress']['city']);
        $this->assertEquals('DE', $result['invoiceAddress']['country']);
        $this->assertEquals('Test Street 123', $result['invoiceAddress']['addressLine1']);
    }

    public function testCreatePrecheckData(): void
    {
        // Arrange
        $order = $this->createBasicOrder();
        $dataFormatter = $this->createDataFormatter($order);
        $orderData = [
            'personalData' => ['email' => 'test@example.com'],
            'basket' => ['netTotal' => 100.00]
        ];

        // Act
        $result = $dataFormatter->createPrecheckData($orderData);

        // Assert
        $this->assertArrayHasKey('requestMode', $result);
        $this->assertEquals('SingleStep', $result['requestMode']);
        $this->assertArrayHasKey('paymentTypeSecurity', $result);
        $this->assertEquals('U', $result['paymentTypeSecurity']);
        $this->assertArrayHasKey('selectedPaymentType', $result);
        $this->assertEquals('', $result['selectedPaymentType']);
        $this->assertArrayHasKey('proofOfInterest', $result);
        $this->assertEquals('AAE', $result['proofOfInterest']);
        $this->assertArrayHasKey('personalData', $result);
        $this->assertArrayHasKey('basket', $result);
    }

    public function testCreateConfirmData(): void
    {
        // Arrange
        $order = $this->createBasicOrder();
        $dataFormatter = $this->createDataFormatter($order);
        $precheckResponse = ['precheckId' => 'PRE123'];
        $orderData = [
            'personalData' => ['email' => 'test@example.com'],
            'basket' => ['netTotal' => 100.00]
        ];

        // Act
        $result = $dataFormatter->createConfirmData($precheckResponse, $orderData);

        // Assert
        $this->assertArrayHasKey('externalOrderId', $result);
        $this->assertEquals('ORDER123', $result['externalOrderId']);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('orderPrecheckResponse', $result);
        $this->assertEquals($precheckResponse, $result['orderPrecheckResponse']);
        $this->assertArrayHasKey('personalData', $result);
        $this->assertArrayHasKey('basket', $result);
    }

    public function testCreateInvoiceData(): void
    {
        // Arrange
        $order = $this->createBasicOrder();
        $dataFormatter = $this->createDataFormatter($order);

        // Act
        $result = $dataFormatter->createInvoiceData();

        // Assert
        $this->assertArrayHasKey('externalOrderId', $result);
        $this->assertEquals('ORDER123', $result['externalOrderId']);
        $this->assertArrayHasKey('externalInvoiceNumber', $result);
        $this->assertEquals('ORDER123', $result['externalInvoiceNumber']);
        $this->assertArrayHasKey('externalInvoiceDisplayName', $result);
        $this->assertStringContainsString('Bestellung ORDER123', $result['externalInvoiceDisplayName']);
        $this->assertArrayHasKey('externalSubOrderId', $result);
        $this->assertEquals('', $result['externalSubOrderId']);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('dueDateOffsetDays', $result);
        $this->assertEquals(14, $result['dueDateOffsetDays']);
        $this->assertArrayHasKey('basket', $result);
    }

    public function testCreateShippingData(): void
    {
        // Arrange
        $orderWithPositions = $this->createMock(Bestellung::class);
        $orderWithPositions->cBestellNr = 'ORDER123';
        $orderWithPositions->dVersandDatum = '2023-01-02 10:00:00';
        $orderWithPositions->Positionen = [
            (object) [
                'kArtikel' => 123,
                'cArtNr' => 'ART123',
                'nAnzahl' => 2
            ],
            (object) [
                'kArtikel' => 0, // Shipping position, should be ignored
                'cArtNr' => 'SHIPPING',
                'nAnzahl' => 1
            ]
        ];

        $formatterWithPositions = new DataFormatter($orderWithPositions);

        // Act
        $result = $formatterWithPositions->createShippingData();

        // Assert
        $this->assertArrayHasKey('externalOrderId', $result);
        $this->assertEquals('ORDER123', $result['externalOrderId']);
        $this->assertArrayHasKey('externalSubOrderId', $result);
        $this->assertEquals('', $result['externalSubOrderId']);
        $this->assertArrayHasKey('basketPositions', $result);
        $this->assertCount(1, $result['basketPositions']);
        $this->assertEquals('ART123', $result['basketPositions'][0]['productId']);
        $this->assertEquals(2, $result['basketPositions'][0]['quantity']);
        $this->assertArrayHasKey('shippingDate', $result);
    }

    public function testCreateShippingDataWithoutShippingDate(): void
    {
        // Arrange
        $orderWithoutShippingDate = $this->createMock(Bestellung::class);
        $orderWithoutShippingDate->cBestellNr = 'ORDER123';
        $orderWithoutShippingDate->dVersandDatum = null;
        $orderWithoutShippingDate->Positionen = [];

        $formatterWithoutDate = new DataFormatter($orderWithoutShippingDate);

        // Act
        $result = $formatterWithoutDate->createShippingData();

        // Assert
        $this->assertArrayHasKey('shippingDate', $result);
        $this->assertNotEmpty($result['shippingDate']);
    }

    public function testConstructorDecodesHtmlEntities(): void
    {
        // Arrange
        $orderWithEntities = $this->createMock(Bestellung::class);
        $orderWithEntities->cBestellNr = 'ORDER&amp;123';
        $orderWithEntities->fGesamtsumme = 100.50;

        // Act
        $formatter = new DataFormatter($orderWithEntities);

        // Assert - Check that HTML entities were decoded
        $reflection = new \ReflectionClass($formatter);
        $property = $reflection->getProperty('order');
        $property->setAccessible(true);
        $decodedOrder = $property->getValue($formatter);

        $this->assertEquals('ORDER&123', $decodedOrder->cBestellNr);
    }
}