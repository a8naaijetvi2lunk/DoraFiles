<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

header('Content-Type: application/json');

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Read changelog file - check both locations
$changelogPath = __DIR__ . '/CHANGELOG.md';
if (!file_exists($changelogPath)) {
    $changelogPath = __DIR__ . '/docs/CHANGELOG.md';
}

if (!file_exists($changelogPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Changelog not found']);
    exit;
}

$changelogContent = file_get_contents($changelogPath);

echo json_encode([
    'success' => true,
    'content' => $changelogContent
]);
