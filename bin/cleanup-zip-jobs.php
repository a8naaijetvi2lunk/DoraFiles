<?php

/**
 * Cleanup expired ZIP jobs
 * This script should be run periodically (e.g., via cron job)
 *
 * Usage: php bin/cleanup-zip-jobs.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();

echo "DoraFiles - ZIP Jobs Cleanup\n";
echo str_repeat("=", 50) . "\n\n";

try {
    $zipJobService = new \App\Services\ZipJobService();

    echo "Cleaning up expired ZIP jobs...\n";
    $cleaned = $zipJobService->cleanupExpiredJobs();

    echo "\n✓ Cleaned $cleaned expired job(s)\n";

    if ($cleaned > 0) {
        echo "\nDeleted ZIP files and removed database entries.\n";
    } else {
        echo "\nNo expired jobs found.\n";
    }

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCleanup completed successfully.\n";
