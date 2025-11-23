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

$itemPath = $_POST['path'] ?? '';
$itemType = $_POST['type'] ?? 'file'; // 'file' or 'dir'
$itemName = $_POST['name'] ?? '';

if (empty($itemPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing path parameter']);
    exit;
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    if ($itemType === 'dir') {
        $ftpService->deleteDirectory($itemPath);
        $message = "Dossier '$itemName' supprimé avec succès";
    } else {
        $ftpService->deleteFile($itemPath);
        $message = "Fichier '$itemName' supprimé avec succès";
    }

    $ftpService->close();

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
}
