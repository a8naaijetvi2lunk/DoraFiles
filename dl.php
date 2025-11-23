<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security headers
\App\Security\SecurityMiddleware::applySecurityHeaders();

// Configure secure session
\App\Security\SecurityMiddleware::configureSecureSession();

// Get token from URL path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

// Extract token from /dl/TOKEN
if (preg_match('#^/dl/([a-f0-9]+)$#', $path, $matches)) {
    $token = $matches[1];

    // Validate token format
    if (!\App\Security\SecurityMiddleware::validateToken($token)) {
        \App\Security\SecurityMiddleware::logSecurityEvent('INVALID_DOWNLOAD_TOKEN', ['token' => substr($token, 0, 10)]);
        http_response_code(404);
        die('Invalid download link');
    }
} else {
    \App\Security\SecurityMiddleware::logSecurityEvent('MALFORMED_DOWNLOAD_URL', ['uri' => $requestUri]);
    http_response_code(404);
    die('Invalid download link');
}

// Redirect to download.php with token
header('Location: /download.php?token=' . urlencode($token));
exit;
