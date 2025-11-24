#!/usr/bin/env php
<?php

/**
 * Cleanup Temporary Files
 * Removes all temporary ZIP files and folders from /tmp
 */

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();

function colorize($text, $color) {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

echo colorize("╔═══════════════════════════════════════╗\n", 'blue');
echo colorize("║   Cleanup Temporary Files            ║\n", 'blue');
echo colorize("╚═══════════════════════════════════════╝\n", 'blue');
echo "\n";

$cleanedCount = 0;
$cleanedSize = 0;
$errors = [];

// 1. Clean temporary ZIP files in /tmp
echo "1. Scanning /tmp for temporary ZIP files...\n";
echo str_repeat("─", 70) . "\n";

$tmpDir = sys_get_temp_dir();
$patterns = [
    'zip_*.zip',           // ZIP files created by the system
    'ftp_zip_*',          // Temporary FTP directories
    'ftp_*',              // Temporary FTP files
    'zip_job_*',          // ZIP job files
];

foreach ($patterns as $pattern) {
    $files = glob($tmpDir . '/' . $pattern);

    if ($files === false) {
        continue;
    }

    foreach ($files as $file) {
        try {
            $size = is_file($file) ? filesize($file) : 0;
            $age = time() - filemtime($file);
            $ageHours = round($age / 3600, 1);

            // Only delete files older than 1 hour
            if ($age > 3600) {
                if (is_file($file)) {
                    if (@unlink($file)) {
                        echo colorize("✓ ", 'green');
                        echo "Deleted file: " . basename($file);
                        echo " (" . formatBytes($size) . ", {$ageHours}h old)\n";
                        $cleanedCount++;
                        $cleanedSize += $size;
                    } else {
                        $errors[] = "Failed to delete file: $file";
                    }
                } elseif (is_dir($file)) {
                    if (rrmdir($file)) {
                        echo colorize("✓ ", 'green');
                        echo "Deleted directory: " . basename($file);
                        echo " ({$ageHours}h old)\n";
                        $cleanedCount++;
                    } else {
                        $errors[] = "Failed to delete directory: $file";
                    }
                }
            } else {
                echo colorize("⊙ ", 'yellow');
                echo "Keeping recent: " . basename($file);
                echo " ({$ageHours}h old)\n";
            }
        } catch (Exception $e) {
            $errors[] = "Error processing $file: " . $e->getMessage();
        }
    }
}

// 2. Clean expired ZIP jobs from database
echo "\n2. Cleaning expired ZIP jobs from database...\n";
echo str_repeat("─", 70) . "\n";

try {
    $zipJobService = new \App\Services\ZipJobService();
    $cleaned = $zipJobService->cleanupExpiredJobs();
    echo colorize("✓ ", 'green');
    echo "Cleaned $cleaned expired ZIP job(s)\n";
} catch (Exception $e) {
    $errors[] = "Failed to clean ZIP jobs: " . $e->getMessage();
    echo colorize("✗ ", 'red');
    echo "Failed to clean ZIP jobs\n";
}

// 3. Clean old ZIP worker logs (keep last 100 lines)
echo "\n3. Rotating ZIP worker log...\n";
echo str_repeat("─", 70) . "\n";

$logFile = __DIR__ . '/../storage/logs/zip-worker.log';
if (file_exists($logFile)) {
    $logSize = filesize($logFile);

    // If log is larger than 1MB, keep only last 100 lines
    if ($logSize > 1024 * 1024) {
        $lines = file($logFile);
        $lastLines = array_slice($lines, -100);
        file_put_contents($logFile, implode('', $lastLines));

        $newSize = filesize($logFile);
        $savedSize = $logSize - $newSize;

        echo colorize("✓ ", 'green');
        echo "Rotated log file: " . formatBytes($logSize) . " → " . formatBytes($newSize);
        echo " (saved " . formatBytes($savedSize) . ")\n";
        $cleanedSize += $savedSize;
    } else {
        echo colorize("⊙ ", 'yellow');
        echo "Log file size OK: " . formatBytes($logSize) . "\n";
    }
} else {
    echo colorize("⊙ ", 'yellow');
    echo "No log file to rotate\n";
}

// Summary
echo "\n" . str_repeat("═", 70) . "\n";
echo colorize("Summary\n", 'blue');
echo str_repeat("═", 70) . "\n";
echo "Files/directories cleaned: " . colorize($cleanedCount, 'green') . "\n";
echo "Total space freed: " . colorize(formatBytes($cleanedSize), 'green') . "\n";

if (!empty($errors)) {
    echo "\n" . colorize("Errors encountered:\n", 'yellow');
    foreach ($errors as $error) {
        echo colorize("✗ ", 'red') . $error . "\n";
    }
}

echo "\n" . colorize("Cleanup complete!\n", 'green');

/**
 * Recursively remove directory
 */
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}
