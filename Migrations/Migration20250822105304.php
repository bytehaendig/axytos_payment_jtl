<?php declare(strict_types=1);
/**
 * New Table for Axytos-Actions.
 *
 * @author Bytehaendig
 * @created Fri, 22 Aug 2025 10:53:04 +0200
 */

namespace Plugin\axytos_payment\Migration;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration
 *
 * Available methods:
 * execute            - returns affected rows
 * fetchOne           - single fetched object
 * fetchAll           - array of fetched objects
 * fetchArray         - array of fetched assoc arrays
 * dropColumn         - drops a column if exists
 * setLocalization    - add localization
 * removeLocalization - remove localization
 * setConfig          - add / update config property
 * removeConfig       - remove config property
 */
class Migration20250822105304 extends Migration implements IMigration
{
    protected $author = 'Bytehaendig';
    protected $description = 'New Table for Axytos-Actions.';

    public function up()
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `axytos_actions` (
            `kAxytosAction` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `kBestellung` int(10) UNSIGNED NOT NULL,
            `cAction` varchar(50) NOT NULL,
            `cStatus` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
            `dCreatedAt` datetime NOT NULL,
            `dFailedAt` datetime NULL,
            `nFailedCount` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `cFailReason` text NULL,
            `dProcessedAt` datetime NULL,
            `cData` json NULL,
            PRIMARY KEY (`kAxytosAction`),
            INDEX `idx_bestellung` (`kBestellung`),
            INDEX `idx_status` (`cStatus`),
            INDEX `idx_bestellung_status` (`kBestellung`, `cStatus`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE IF NOT EXISTS `axytos_actionslog` (
            `kAxytosActionsLog` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `kBestellung` int(10) UNSIGNED NOT NULL,
            `cAction` varchar(50) NOT NULL,
            `dProcessedAt` datetime NOT NULL,
            `cLevel` enum('debug','info','warning','error') NOT NULL DEFAULT 'info',
            `cMessage` text NOT NULL,
            PRIMARY KEY (`kAxytosActionsLog`),
            INDEX `idx_bestellung` (`kBestellung`),
            INDEX `idx_processed_at` (`dProcessedAt`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `axytos_actionslog`");
        $this->execute("DROP TABLE IF EXISTS `axytos_actions`");
    }
}
