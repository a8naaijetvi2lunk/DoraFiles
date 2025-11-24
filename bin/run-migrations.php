<?php

/**
 * Migration Runner
 * Executes all database migrations in order
 *
 * Usage: php bin/run-migrations.php [--check-only]
 */

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();

// Color output for terminal
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
echo colorize("║   Dora Files - Migration Runner      ║\n", 'blue');
echo colorize("╚═══════════════════════════════════════╝\n", 'blue');
echo "\n";

$checkOnly = in_array('--check-only', $argv);

if ($checkOnly) {
    echo colorize("Mode: Check Only (no migrations will be executed)\n\n", 'yellow');
}

// Define migrations in order
$migrations = [
    [
        'name' => '001 - Initial Schema',
        'file' => __DIR__ . '/../database/migrations/001_initial_schema.php',
        'description' => 'Creates users, shared_links, rate_limits, zip_jobs tables (with optimized indexes)'
    ],
    [
        'name' => '002 - Profile Feature',
        'file' => __DIR__ . '/../database/migrations/002_profile_feature.php',
        'description' => 'Creates ftp_connections, activity_logs tables (with optimized indexes)'
    ],
    [
        'name' => '003 - Two-Factor Auth',
        'file' => __DIR__ . '/../database/migrations/003_two_factor_auth.php',
        'description' => 'Adds 2FA columns to users table'
    ]
];

// Check database connection
echo "Checking database connection...\n";
try {
    $pdo = db();
    echo colorize("✓ Database connection successful\n\n", 'green');
} catch (Exception $e) {
    echo colorize("✗ Database connection failed: " . $e->getMessage() . "\n", 'red');
    exit(1);
}

// Check which migrations have been applied
echo "Checking migration status...\n";
echo str_repeat("─", 70) . "\n";
printf("%-35s %-20s %s\n", "Migration", "Status", "Tables");
echo str_repeat("─", 70) . "\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Check migration 001
$migration1Applied = in_array('users', $tables) && in_array('shared_links', $tables) && in_array('zip_jobs', $tables);
$status1 = $migration1Applied ? colorize('✓ Applied', 'green') : colorize('✗ Pending', 'yellow');
printf("%-35s %-30s %s\n", "001 - Initial Schema", $status1, "users, shared_links, rate_limits, zip_jobs");

// Check migration 002
$migration2Applied = in_array('ftp_connections', $tables) && in_array('activity_logs', $tables);
$status2 = $migration2Applied ? colorize('✓ Applied', 'green') : colorize('✗ Pending', 'yellow');
printf("%-35s %-30s %s\n", "002 - Profile Feature", $status2, "ftp_connections, activity_logs");

// Check migration 003
$columns = [];
if (in_array('users', $tables)) {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
}
$migration3Applied = in_array('two_factor_enabled', $columns);
$status3 = $migration3Applied ? colorize('✓ Applied', 'green') : colorize('✗ Pending', 'yellow');
printf("%-35s %-30s %s\n", "003 - Two-Factor Auth", $status3, "2FA columns");

echo str_repeat("─", 70) . "\n\n";

if ($checkOnly) {
    echo colorize("Check complete. Use 'php bin/run-migrations.php' to apply pending migrations.\n", 'blue');
    exit(0);
}

// Apply pending migrations
$pendingMigrations = [];
if (!$migration1Applied) $pendingMigrations[] = 0;
if (!$migration2Applied) $pendingMigrations[] = 1;
if (!$migration3Applied) $pendingMigrations[] = 2;

if (empty($pendingMigrations)) {
    echo colorize("✓ All migrations are up to date!\n", 'green');
    exit(0);
}

echo colorize("Found " . count($pendingMigrations) . " pending migration(s)\n\n", 'yellow');

// Confirm before proceeding
echo "Do you want to apply these migrations? (yes/no): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo colorize("\nMigration cancelled.\n", 'yellow');
    exit(0);
}

echo "\n";

// Execute pending migrations
foreach ($pendingMigrations as $index) {
    $migration = $migrations[$index];

    echo colorize("Running: {$migration['name']}\n", 'blue');
    echo "  {$migration['description']}\n";
    echo str_repeat("─", 70) . "\n";

    // Execute migration script
    ob_start();
    include $migration['file'];
    $output = ob_get_clean();

    echo $output;
    echo str_repeat("─", 70) . "\n\n";
}

echo colorize("╔═══════════════════════════════════════╗\n", 'green');
echo colorize("║   All migrations completed!          ║\n", 'green');
echo colorize("╚═══════════════════════════════════════╝\n", 'green');
echo "\n";

// Final verification
echo "Running final verification...\n";
exec('php ' . __DIR__ . '/check-setup.php', $output, $returnCode);
echo "\n";
foreach ($output as $line) {
    echo $line . "\n";
}

if ($returnCode === 0) {
    echo "\n" . colorize("✓ Setup verification passed!\n", 'green');
} else {
    echo "\n" . colorize("⚠ Setup verification found some issues. Please review above.\n", 'yellow');
}
