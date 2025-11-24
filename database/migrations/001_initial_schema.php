<?php

// This migration is included from setup/index.php which already loaded autoload.php
// If run standalone, uncomment the lines below:
// require_once __DIR__ . '/../../vendor/autoload.php';
// require_once __DIR__ . '/../../app/helpers.php';

// Use the $pdo variable passed from setup/index.php
try {
    // $pdo is already defined in the setup context

    echo "Starting database migration...\n";

    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            ftp_host TEXT NOT NULL,
            ftp_port TEXT NOT NULL,
            ftp_username TEXT NOT NULL,
            ftp_password TEXT NOT NULL,
            ftp_base_path TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created users table\n";

    // Create shared_links table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shared_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ftp_connection_id INT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NULL,
            file_path TEXT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT NOT NULL,
            expires_at TIMESTAMP NULL,
            download_count INT DEFAULT 0,
            last_downloaded_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_ftp_connection_id (ftp_connection_id),
            INDEX idx_expires_at (expires_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created shared_links table\n";

    // Create rate_limits table for download rate limiting
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_action (ip_address, action),
            INDEX idx_window (window_start),
            INDEX idx_rate_limit_check (ip_address, action, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created rate_limits table\n";

    // Create zip_jobs table for async ZIP generation with progress tracking
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zip_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            folder_path TEXT NOT NULL,
            folder_name VARCHAR(255) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            progress_percent TINYINT UNSIGNED DEFAULT 0,
            total_files INT DEFAULT 0,
            processed_files INT DEFAULT 0,
            total_size_bytes BIGINT DEFAULT 0,
            processed_size_bytes BIGINT DEFAULT 0,
            zip_file_path TEXT NULL,
            error_message TEXT NULL,
            estimated_time_remaining INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_status (user_id, status),
            INDEX idx_expires_at (expires_at),
            INDEX idx_status (status),
            INDEX idx_expires_status (expires_at, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created zip_jobs table\n";

    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
