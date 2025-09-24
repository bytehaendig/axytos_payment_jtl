<?php declare(strict_types=1);

/**
 * Test bootstrap for Axytos Payment Plugin
 *
 * This file sets up the test environment for running unit tests.
 * It loads the necessary dependencies and configures the test environment.
 */

// Load Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    throw new RuntimeException('Composer autoloader not found. Run "composer install" first.');
}
require_once $autoloader;

// Manually load PHPUnit autoload if it exists
$phpunitAutoload = __DIR__ . '/../vendor/phpunit/phpunit/vendor/autoload.php';
if (file_exists($phpunitAutoload)) {
    require_once $phpunitAutoload;
}

// Load JTL-Shop bootstrap if available (for integration tests)
// This allows access to JTL Shop classes in tests
$jtlBootstrap = __DIR__ . '/../../includes/bootstrap.php';
if (file_exists($jtlBootstrap)) {
    require_once $jtlBootstrap;
}

// Set up error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants if needed
if (!defined('TEST_ENVIRONMENT')) {
    define('TEST_ENVIRONMENT', true);
}