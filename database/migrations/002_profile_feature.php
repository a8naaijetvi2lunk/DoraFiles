<?php

// This migration is included from setup/index.php which already loaded autoload.php
// If run standalone, uncomment the lines below:
// require_once __DIR__ . '/../../vendor/autoload.php';
// require_once __DIR__ . '/../../app/helpers.php';

// Use the $pdo variable passed from setup/index.php
try {
    // $pdo is already defined in the setup context

    echo "Starting profile feature migration...\n";

    // Modify users table - add profile fields
    echo "Updating users table...\n";

    // Check and add columns if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('active_ftp_connection_id', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN active_ftp_connection_id INT NULL");
        echo "  - Added active_ftp_connection_id column\n";
    }

    if (!in_array('last_login_at', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL");
        echo "  - Added last_login_at column\n";
    }

    if (!in_array('last_login_ip', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_ip VARCHAR(45) NULL");
        echo "  - Added last_login_ip column\n";
    }

    echo "✓ Updated users table with profile fields\n";

    // Create ftp_connections table for multi-FTP support
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ftp_connections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            connection_name VARCHAR(100) NOT NULL,
            ftp_host TEXT NOT NULL,
            ftp_port TEXT NOT NULL,
            ftp_username TEXT NOT NULL,
            ftp_password TEXT NOT NULL,
            ftp_base_path TEXT,
            is_default BOOLEAN DEFAULT 0,
            last_used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_default (is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created ftp_connections table\n";

    // Add foreign key constraint to shared_links table for ftp_connection_id
    try {
        $pdo->exec("ALTER TABLE shared_links ADD CONSTRAINT fk_shared_links_ftp_connection FOREIGN KEY (ftp_connection_id) REFERENCES ftp_connections(id) ON DELETE SET NULL");
        echo "✓ Added foreign key constraint on shared_links.ftp_connection_id\n";
    } catch (PDOException $e) {
        // Ignore if already exists
        if (strpos($e->getMessage(), 'Duplicate foreign key') === false && strpos($e->getMessage(), 'already exists') === false) {
            echo "Note: Could not add foreign key - " . $e->getMessage() . "\n";
        }
    }

    // Migrate existing FTP credentials to ftp_connections table
    echo "Migrating existing FTP credentials...\n";

    $users = $pdo->query("SELECT id, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path FROM users WHERE ftp_host IS NOT NULL AND ftp_host != ''")->fetchAll();

    $migratedCount = 0;
    foreach ($users as $user) {
        // Check if already migrated
        $check = $pdo->prepare("SELECT COUNT(*) FROM ftp_connections WHERE user_id = ?");
        $check->execute([$user['id']]);

        if ($check->fetchColumn() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ftp_connections (user_id, connection_name, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path, is_default)
                VALUES (?, 'Default Connection', ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $user['id'],
                $user['ftp_host'],
                $user['ftp_port'],
                $user['ftp_username'],
                $user['ftp_password'],
                $user['ftp_base_path']
            ]);

            // Set active_ftp_connection_id
            $connectionId = $pdo->lastInsertId();
            $updateStmt = $pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?");
            $updateStmt->execute([$connectionId, $user['id']]);

            $migratedCount++;
        }
    }

    echo "✓ Migrated {$migratedCount} existing FTP connection(s)\n";

    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NULL,
            entity_name VARCHAR(255) NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created activity_logs table\n";

    echo "\nProfile feature migration completed successfully!\n";
    echo "\nNew features available:\n";
    echo "  - Multi-FTP connections management\n";
    echo "  - Activity logging\n";
    echo "  - User profile page\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
