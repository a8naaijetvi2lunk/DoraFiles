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

$user = auth();
$currentPath = $_POST['current_path'] ?? '/';
$folderName = trim($_POST['folder_name'] ?? '');

if (empty($folderName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Folder name is required']);
    exit;
}

// Validate folder name (no special characters that could cause issues)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $folderName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom de dossier invalide. Utilisez uniquement des lettres, chiffres, tirets et underscores.']);
    exit;
}

// Build full path
$fullPath = rtrim($currentPath, '/') . '/' . $folderName;

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    $ftpService->createDirectory($fullPath);
    $ftpService->close();

    echo json_encode([
        'success' => true,
        'message' => "Dossier '$folderName' créé avec succès",
        'folder_name' => $folderName
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la création du dossier: ' . $e->getMessage()]);
}
