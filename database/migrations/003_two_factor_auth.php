<?php

/**
 * 2FA (Two-Factor Authentication) Migration
 *
 * This migration adds the necessary database columns for 2FA functionality
 * Run this AFTER migrate-profile.php
 *
 * Usage: php migrate-2fa.php
 */

// This migration is included from setup/index.php which already loaded autoload.php
// If run standalone, uncomment the lines below:
// require_once __DIR__ . '/../../vendor/autoload.php';
// require_once __DIR__ . '/../../app/helpers.php';

// Use the $pdo variable passed from setup/index.php
try {
    // $pdo is already defined in the setup context

    echo "Starting 2FA migration...\n";

    // Check and add 2FA columns to users table
    echo "Updating users table with 2FA columns...\n";

    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    // Add two_factor_secret column
    if (!in_array('two_factor_secret', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL COMMENT 'Encrypted TOTP secret'");
        echo "  ✓ Added two_factor_secret column\n";
    } else {
        echo "  - two_factor_secret column already exists\n";
    }

    // Add two_factor_enabled column
    if (!in_array('two_factor_enabled', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 COMMENT '1 if 2FA is enabled'");
        echo "  ✓ Added two_factor_enabled column\n";
    } else {
        echo "  - two_factor_enabled column already exists\n";
    }

    // Add two_factor_backup_codes column
    if (!in_array('two_factor_backup_codes', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_backup_codes TEXT NULL COMMENT 'JSON array of hashed backup codes'");
        echo "  ✓ Added two_factor_backup_codes column\n";
    } else {
        echo "  - two_factor_backup_codes column already exists\n";
    }

    // Add index on two_factor_enabled for faster queries
    try {
        $pdo->exec("CREATE INDEX idx_two_factor_enabled ON users(two_factor_enabled)");
        echo "  ✓ Added index on two_factor_enabled\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "  - Index idx_two_factor_enabled already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n✓ 2FA migration completed successfully!\n";
    echo "\nNew features available:\n";
    echo "  - Two-Factor Authentication (TOTP)\n";
    echo "  - Backup codes for account recovery\n";
    echo "  - QR code generation for authenticator apps\n";
    echo "\nUsers can enable 2FA from their profile settings.\n";

} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "  1. Ensure migrate.php has been run first\n";
    echo "  2. Ensure migrate-profile.php has been run\n";
    echo "  3. Check database connection settings in .env\n";
    echo "  4. Verify database user has ALTER privileges\n";
    exit(1);
}
