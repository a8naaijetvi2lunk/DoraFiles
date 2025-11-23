<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/browse.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$user = auth();
$filePath = $_POST['file_path'] ?? '';
$fileName = $_POST['file_name'] ?? '';
$fileSize = (int) ($_POST['file_size'] ?? 0);
$currentPath = $_POST['current_path'] ?? '/';

// Get the active FTP connection ID (from session or user's active connection)
$ftpConnectionService = new \App\Services\FTPConnectionService();
$browseConnectionId = $_SESSION['browse_ftp_connection_id'] ?? $user['active_ftp_connection_id'] ?? null;

// If no connection ID, get the first one
if (!$browseConnectionId) {
    $allConnections = $ftpConnectionService->getUserConnections($user['id']);
    if (!empty($allConnections)) {
        $browseConnectionId = $allConnections[0]['id'];
    }
}

$linkService = new \App\Services\LinkService();

try {
    // Create permanent link without expiration or password
    $result = $linkService->createLink(
        $user['id'],
        $browseConnectionId,  // Pass the FTP connection ID
        $filePath,
        $fileName,
        $fileSize,
        null,  // No expiration
        null   // No password
    );

    // Redirect back to browse with success message
    $_SESSION['link_created'] = $result['url'];
    redirect('/links.php');

} catch (Exception $e) {
    $_SESSION['error'] = 'Erreur lors de la création du lien: ' . $e->getMessage();
    redirect('/browse.php?path=' . urlencode($currentPath));
}
