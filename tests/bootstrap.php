<?php

/**
 * PHPUnit Bootstrap File
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
if (file_exists(__DIR__ . '/../.env.testing')) {
    loadEnv(__DIR__ . '/../.env.testing');
} else {
    loadEnv();
}

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
