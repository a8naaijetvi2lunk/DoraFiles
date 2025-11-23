<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$sourcePath = $_POST['source_path'] ?? '';
$destinationDir = $_POST['destination_dir'] ?? '/';
$itemName = $_POST['item_name'] ?? '';

if (empty($sourcePath) || empty($itemName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Build destination path
$destinationPath = rtrim($destinationDir, '/') . '/' . $itemName;

// Prevent moving to same location
if ($sourcePath === $destinationPath) {
    http_response_code(400);
    echo json_encode(['error' => 'Source and destination are the same']);
    exit;
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    $ftpService->moveItem($sourcePath, $destinationPath);
    $ftpService->close();

    echo json_encode([
        'success' => true,
        'message' => "Item moved successfully"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors du déplacement: ' . $e->getMessage()]);
}
