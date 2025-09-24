<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for Axytos Payment Plugin tests
 *
 * This class provides common setup and utilities for all test cases.
 * Extend this class for your test cases to get access to shared functionality.
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Common test setup can go here
        // For example: reset static variables, clear caches, etc.
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Common cleanup can go here
        // For example: close database connections, clear mocks, etc.
    }

    /**
     * Helper method to create mock objects easily
     *
     * @param string $className
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createMock(string $className): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Helper method to assert that an array has specific keys
     *
     * @param array $keys
     * @param array $array
     * @param string $message
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should have key '{$key}'");
        }
    }
}