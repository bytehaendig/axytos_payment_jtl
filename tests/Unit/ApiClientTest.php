<?php declare(strict_types=1);

namespace Tests\Unit;

use Plugin\axytos_payment\helpers\ApiClient;
use Tests\BaseTestCase;

final class ApiClientTest extends BaseTestCase
{
    private function createMockApiClient(?string $apiKey = null, bool $useSandbox = true, ?string $pluginVersion = null): ApiClient
    {
        $apiKey = $apiKey ?? 'mock-api-key';
        $pluginVersion = $pluginVersion ?? '1.0.0';

        return new ApiClient($apiKey, $useSandbox, $pluginVersion);
    }

    public function testConstructorSetsProperties(): void
    {
        // Arrange
        $apiKey = 'test-api-key';
        $useSandbox = false;
        $pluginVersion = '2.0.0';

        // Act
        $apiClient = new ApiClient($apiKey, $useSandbox, $pluginVersion);

        // Assert
        $this->assertEquals($apiKey, $this->getPrivateProperty($apiClient, 'AxytosAPIKey'));
        $this->assertEquals('https://api.axytos.com/api/v1', $this->getPrivateProperty($apiClient, 'BaseUrl'));
        $this->assertStringContainsString('AxytosJTLShopPlugin/2.0.0', $this->getPrivateProperty($apiClient, 'UserAgent'));
    }

    public function testConstructorUsesSandboxUrlByDefault(): void
    {
        // Arrange & Act
        $apiClient = new ApiClient('test-key');

        // Assert
        $this->assertEquals('https://api-sandbox.axytos.com/api/v1', $this->getPrivateProperty($apiClient, 'BaseUrl'));
    }

    public function testMakeUserAgentWithVersion(): void
    {
        // Arrange
        $apiClient = $this->createMockApiClient('key', true, '1.5.0');

        // Act
        $userAgent = $this->callPrivateMethod($apiClient, 'makeUserAgent', ['1.5.0']);

        // Assert
        $this->assertStringContainsString('AxytosJTLShopPlugin/1.5.0', $userAgent);
        $this->assertStringContainsString('PHP:', $userAgent);
        $this->assertStringContainsString('JTL:', $userAgent);
    }

    public function testMakeUserAgentWithUnknownVersion(): void
    {
        // Arrange
        $apiClient = $this->createMockApiClient('key', true, null);

        // Act
        $userAgent = $this->callPrivateMethod($apiClient, 'makeUserAgent', [null]);

        // Assert
        $this->assertTrue(str_contains($userAgent, 'AxytosJTLShopPlugin/unknown'));
    }

    // TODO: test for API calls
}
