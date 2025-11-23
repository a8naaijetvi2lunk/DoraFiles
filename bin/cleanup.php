<?php

/**
 * Script de nettoyage automatique
 * À exécuter via cron: 0 2 * * * php /path/to/cleanup.php
 */

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

try {
    $pdo = db();

    echo "[" . date('Y-m-d H:i:s') . "] Début du nettoyage...\n";

    // Delete expired links
    $stmt = $pdo->prepare("
        DELETE FROM shared_links
        WHERE expires_at < NOW()
        AND expires_at IS NOT NULL
    ");
    $stmt->execute();
    $expiredLinks = $stmt->rowCount();
    echo "  - Liens expirés supprimés: $expiredLinks\n";

    // Delete old revoked links (older than 30 days)
    $stmt = $pdo->prepare("
        DELETE FROM shared_links
        WHERE revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND revoked_at IS NOT NULL
    ");
    $stmt->execute();
    $revokedLinks = $stmt->rowCount();
    echo "  - Liens révoqués (>30j) supprimés: $revokedLinks\n";

    // Clean old rate limit entries
    $stmt = $pdo->prepare("
        DELETE FROM rate_limits
        WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $rateLimits = $stmt->rowCount();
    echo "  - Entrées rate limit nettoyées: $rateLimits\n";

    // Clean temporary files older than 24 hours
    $tempDir = sys_get_temp_dir();
    $cleanedFiles = 0;
    $cleanedSize = 0;

    // Find and delete old temporary files (ftp_*, zip_*)
    $tempFiles = glob($tempDir . '/ftp_*');
    $zipFiles = glob($tempDir . '/zip_*');
    $allTempFiles = array_merge($tempFiles ?: [], $zipFiles ?: []);

    foreach ($allTempFiles as $file) {
        if (is_file($file)) {
            $fileAge = time() - filemtime($file);
            // Delete files older than 24 hours (86400 seconds)
            if ($fileAge > 86400) {
                $fileSize = filesize($file);
                if (@unlink($file)) {
                    $cleanedFiles++;
                    $cleanedSize += $fileSize;
                }
            }
        }
    }

    // Clean empty temporary directories
    $tempDirs = glob($tempDir . '/ftp_zip_*');
    foreach ($tempDirs ?: [] as $dir) {
        if (is_dir($dir) && count(scandir($dir)) == 2) { // only . and ..
            @rmdir($dir);
        }
    }

    $cleanedSizeMB = round($cleanedSize / 1024 / 1024, 2);
    echo "  - Fichiers temporaires nettoyés: $cleanedFiles ($cleanedSizeMB MB)\n";

    echo "[" . date('Y-m-d H:i:s') . "] Nettoyage terminé avec succès\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
