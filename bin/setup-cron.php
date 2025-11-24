#!/usr/bin/env php
<?php

/**
 * Automatic Cron Job Setup Script
 * Generates and installs cron jobs for DoraFiles maintenance tasks
 *
 * Usage:
 *   php bin/setup-cron.php            # Interactive mode
 *   php bin/setup-cron.php --auto     # Auto install (requires sudo)
 *   php bin/setup-cron.php --show     # Show crontab without installing
 */

$projectRoot = dirname(__DIR__);
$phpBinary = PHP_BINARY;

// Color output
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
echo colorize("║   DoraFiles - Cron Setup             ║\n", 'blue');
echo colorize("╚═══════════════════════════════════════╝\n", 'blue');
echo "\n";

// Define cron jobs
$cronJobs = [
    [
        'schedule' => '0 2 * * *',
        'script' => 'bin/cleanup.php',
        'description' => 'Daily cleanup (expired links, old rate limits)'
    ],
    [
        'schedule' => '10 2 * * *',
        'script' => 'bin/cleanup-zip-jobs.php',
        'description' => 'Daily ZIP jobs cleanup (expired ZIPs)'
    ],
    [
        'schedule' => '15 2 * * *',
        'script' => 'bin/cleanup-logs.php',
        'description' => 'Daily log rotation and cleanup'
    ],
    [
        'schedule' => '0 3 * * *',
        'script' => 'bin/cleanup-temp-files.php',
        'description' => 'Daily temp files cleanup (FTP, ZIP temporaries)'
    ]
];

// Check mode
$mode = $argv[1] ?? 'interactive';

if ($mode === '--show') {
    echo "Generated crontab entries:\n";
    echo str_repeat("─", 70) . "\n\n";

    foreach ($cronJobs as $job) {
        echo colorize("# {$job['description']}\n", 'blue');
        echo "{$job['schedule']} {$phpBinary} {$projectRoot}/{$job['script']}\n\n";
    }

    exit(0);
}

// Generate crontab content
$crontabContent = "# DoraFiles Maintenance Tasks\n";
$crontabContent .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";

foreach ($cronJobs as $job) {
    $crontabContent .= "# {$job['description']}\n";
    $crontabContent .= "{$job['schedule']} {$phpBinary} {$projectRoot}/{$job['script']}\n\n";
}

// Save to temporary file
$tempFile = sys_get_temp_dir() . '/dorafiles-crontab-' . uniqid() . '.txt';
file_put_contents($tempFile, $crontabContent);

echo "Cron jobs configuration:\n";
echo str_repeat("─", 70) . "\n";
foreach ($cronJobs as $job) {
    echo colorize("✓ ", 'green') . "{$job['description']}\n";
    echo "  Schedule: {$job['schedule']} (Daily at " . date('H:i', strtotime(explode(' ', $job['schedule'])[1] . ':' . explode(' ', $job['schedule'])[0])) . ")\n";
    echo "  Script:   {$job['script']}\n\n";
}
echo str_repeat("─", 70) . "\n\n";

if ($mode === '--auto') {
    echo colorize("Auto-installation mode\n\n", 'yellow');

    // Check if crontab exists
    $currentCrontab = shell_exec('crontab -l 2>/dev/null');

    if ($currentCrontab && strpos($currentCrontab, 'DoraFiles Maintenance Tasks') !== false) {
        echo colorize("⚠ DoraFiles cron jobs already installed!\n", 'yellow');
        echo "To reinstall, remove existing jobs first with:\n";
        echo "  crontab -e\n\n";
        unlink($tempFile);
        exit(1);
    }

    // Append to existing crontab
    if ($currentCrontab) {
        file_put_contents($tempFile, $currentCrontab . "\n" . $crontabContent);
    }

    // Install crontab
    exec("crontab {$tempFile}", $output, $returnCode);

    if ($returnCode === 0) {
        echo colorize("✓ Cron jobs installed successfully!\n\n", 'green');
        echo "To view installed cron jobs:\n";
        echo "  crontab -l\n\n";
        echo "To edit cron jobs:\n";
        echo "  crontab -e\n\n";
    } else {
        echo colorize("✗ Failed to install cron jobs\n", 'red');
        echo "Error code: {$returnCode}\n\n";
        echo "Manual installation required. Run:\n";
        echo "  crontab -e\n";
        echo "Then paste the content from: {$tempFile}\n\n";
        exit(1);
    }

    unlink($tempFile);
    exit(0);
}

// Interactive mode
echo "Installation options:\n\n";
echo "1. Automatic installation (recommended)\n";
echo "   - Installs cron jobs for current user\n";
echo "   - Requires no manual intervention\n\n";

echo "2. Manual installation\n";
echo "   - Saves crontab to file\n";
echo "   - You install it yourself with: crontab {$tempFile}\n\n";

echo "3. Show crontab only\n";
echo "   - Displays the crontab entries\n";
echo "   - No installation\n\n";

echo "4. Cancel\n\n";

echo "Choose an option (1-4): ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

switch ($choice) {
    case '1':
        echo "\n";
        // Check if crontab exists
        $currentCrontab = shell_exec('crontab -l 2>/dev/null');

        if ($currentCrontab && strpos($currentCrontab, 'DoraFiles Maintenance Tasks') !== false) {
            echo colorize("⚠ DoraFiles cron jobs already installed!\n", 'yellow');
            echo "\nDo you want to reinstall? (yes/no): ";
            $handle = fopen("php://stdin", "r");
            $confirm = trim(fgets($handle));
            fclose($handle);

            if (strtolower($confirm) !== 'yes') {
                echo "\nCancelled.\n";
                unlink($tempFile);
                exit(0);
            }

            // Remove old DoraFiles jobs
            $lines = explode("\n", $currentCrontab);
            $newCrontab = '';
            $skip = false;

            foreach ($lines as $line) {
                if (strpos($line, 'DoraFiles Maintenance Tasks') !== false) {
                    $skip = true;
                    continue;
                }
                if ($skip && empty(trim($line))) {
                    $skip = false;
                    continue;
                }
                if (!$skip && !empty(trim($line)) && strpos($line, 'DoraFiles') === false) {
                    $newCrontab .= $line . "\n";
                }
            }

            $currentCrontab = trim($newCrontab);
        }

        // Append to existing crontab
        if ($currentCrontab) {
            file_put_contents($tempFile, $currentCrontab . "\n\n" . $crontabContent);
        }

        // Install crontab
        exec("crontab {$tempFile}", $output, $returnCode);

        if ($returnCode === 0) {
            echo "\n" . colorize("✓ Cron jobs installed successfully!\n\n", 'green');
            echo "To view installed cron jobs:\n";
            echo "  crontab -l\n\n";
            echo "To edit cron jobs:\n";
            echo "  crontab -e\n\n";
        } else {
            echo "\n" . colorize("✗ Failed to install cron jobs\n", 'red');
            echo "Error code: {$returnCode}\n\n";
            echo "Try manual installation instead.\n";
            exit(1);
        }
        break;

    case '2':
        echo "\n" . colorize("Crontab saved to: {$tempFile}\n\n", 'green');
        echo "To install manually, run:\n";
        echo "  crontab {$tempFile}\n\n";
        echo "Or edit existing crontab:\n";
        echo "  crontab -e\n";
        echo "And paste the content from: {$tempFile}\n\n";
        exit(0);

    case '3':
        echo "\n";
        echo file_get_contents($tempFile);
        echo "\n";
        unlink($tempFile);
        exit(0);

    case '4':
        echo "\nCancelled.\n";
        unlink($tempFile);
        exit(0);

    default:
        echo "\nInvalid choice.\n";
        unlink($tempFile);
        exit(1);
}

unlink($tempFile);
