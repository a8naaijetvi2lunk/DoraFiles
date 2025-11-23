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

$oldPath = $_POST['old_path'] ?? '';
$newName = trim($_POST['new_name'] ?? '');
$currentDir = $_POST['current_dir'] ?? '/';

if (empty($oldPath) || empty($newName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Validate new name (no special characters that could cause issues)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom invalide. Utilisez uniquement des lettres, chiffres, tirets et underscores.']);
    exit;
}

// Build new path (same directory, new name)
$pathParts = explode('/', $oldPath);
array_pop($pathParts); // Remove old filename
$newPath = (empty($pathParts) ? '' : implode('/', $pathParts) . '/') . $newName;

// Prevent renaming to same name
if ($oldPath === $newPath) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nouveau nom est identique à l\'ancien']);
    exit;
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    $ftpService->moveItem($oldPath, $newPath);
    $ftpService->close();

    echo json_encode([
        'success' => true,
        'message' => "Renommé avec succès en '$newName'",
        'new_name' => $newName
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors du renommage: ' . $e->getMessage()]);
}
