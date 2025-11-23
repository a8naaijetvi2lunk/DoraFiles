<?php

/**
 * Cleanup old activity logs
 * This script should be run periodically via cron
 * Example cron: 0 2 * * * /usr/bin/php /path/to/cleanup-logs.php
 */

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

use App\Services\ActivityLogService;

try {
    $activityLog = new ActivityLogService();

    // Keep logs for 90 days (configurable)
    $daysToKeep = (int)(env('ACTIVITY_LOG_RETENTION_DAYS', 90));

    echo "Starting activity logs cleanup...\n";
    echo "Keeping logs from the last {$daysToKeep} days\n\n";

    $deleted = $activityLog->cleanupOldLogs($daysToKeep);

    echo "✓ Deleted {$deleted} old activity log(s)\n";
    echo "Cleanup completed successfully!\n";

} catch (Exception $e) {
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}
