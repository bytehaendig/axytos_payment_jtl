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

// Define JTL-Shop constants that are needed for tests
// These are normally defined in includes/defines_inc.php
if (!defined('PAGE_UNBEKANNT')) {
    define('PAGE_UNBEKANNT', 0);
    define('PAGE_ARTIKEL', 1);
    define('PAGE_ARTIKELLISTE', 2);
    define('PAGE_WARENKORB', 3);
    define('PAGE_MEINKONTO', 4);
    define('PAGE_KONTAKT', 5);
    define('PAGE_UMFRAGE', 6);
    define('PAGE_NEWS', 7);
    define('PAGE_NEWSLETTER', 8);
    define('PAGE_LOGIN', 9);
    define('PAGE_REGISTRIERUNG', 10);
    define('PAGE_BESTELLVORGANG', 11);
    define('PAGE_BEWERTUNG', 12);
    define('PAGE_DRUCKANSICHT', 13);
    define('PAGE_PASSWORTVERGESSEN', 14);
    define('PAGE_WARTUNG', 15);
    define('PAGE_WUNSCHLISTE', 16);
    define('PAGE_VERGLEICHSLISTE', 17);
    define('PAGE_STARTSEITE', 18);
    define('PAGE_VERSAND', 19);
}

// Load JTL-Shop bootstrap if available (for integration tests)
// This allows access to JTL Shop classes in tests
$jtlBootstrap = __DIR__ . '/../../../includes/bootstrap.php';
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