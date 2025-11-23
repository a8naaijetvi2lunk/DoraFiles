<?php

// Check if application is installed
if (!file_exists(__DIR__ . '/.env') || filesize(__DIR__ . '/.env') === 0) {
    header('Location: /setup/index.php');
    exit;
}

// Check if .env is configured (not just example)
$envContent = file_get_contents(__DIR__ . '/.env');
if (strpos($envContent, 'CHANGE_THIS') !== false || strpos($envContent, 'DB_DATABASE=') === false) {
    header('Location: /setup/index.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Redirect to dashboard if authenticated, otherwise to login
if (isAuthenticated()) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;
