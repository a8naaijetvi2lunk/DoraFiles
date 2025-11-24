<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

use App\Services\FTPConnectionService;
use App\Services\ActivityLogService;

$user = auth();
$path = \App\Security\SecurityMiddleware::validatePath($_GET['path'] ?? '/');

// Handle FTP connection switch for browsing (temporary, doesn't change profile default)
if (isset($_GET['switch_ftp']) && is_numeric($_GET['switch_ftp'])) {
    $ftpService = new FTPConnectionService();
    $requestedConnection = $ftpService->getConnection((int)$_GET['switch_ftp'], $user['id']);

    if ($requestedConnection) {
        // Store in session temporarily for browsing
        $_SESSION['browse_ftp_connection_id'] = (int)$_GET['switch_ftp'];

        // Update last_used_at - SECURITY FIX: Use validated integer and add user_id check
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE ftp_connections SET last_used_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$_GET['switch_ftp'], $user['id']]);

        // Log activity
        $activityLog = new ActivityLogService();
        $activityLog->log($user['id'], 'ftp_connection_switched', 'ftp_connection', $requestedConnection['connection_name'], "Browsing files on '{$requestedConnection['connection_name']}'");

        // Redirect to clean URL
        redirect('/browse.php?path=' . urlencode($path));
    }
}

// Get all user's FTP connections
$ftpConnectionService = new FTPConnectionService();
$allConnections = $ftpConnectionService->getUserConnections($user['id']);

// Determine which connection to use for browsing
$browseConnectionId = $_SESSION['browse_ftp_connection_id'] ?? $user['active_ftp_connection_id'] ?? null;

// Get the full connection with decrypted credentials (including password)
$currentConnection = null;
if ($browseConnectionId) {
    $currentConnection = $ftpConnectionService->getConnection($browseConnectionId, $user['id']);
}

// Fallback to first connection if none found
if (!$currentConnection && !empty($allConnections)) {
    $browseConnectionId = $allConnections[0]['id'];
    $currentConnection = $ftpConnectionService->getConnection($browseConnectionId, $user['id']);
    $_SESSION['browse_ftp_connection_id'] = $browseConnectionId;
}

// Prepare user data with selected connection credentials
$browseUser = $user;
if ($currentConnection) {
    $browseUser['ftp_host_decrypted'] = $currentConnection['ftp_host_decrypted'];
    $browseUser['ftp_port_decrypted'] = $currentConnection['ftp_port_decrypted'];
    $browseUser['ftp_username_decrypted'] = $currentConnection['ftp_username_decrypted'];
    $browseUser['ftp_password_decrypted'] = $currentConnection['ftp_password_decrypted'];
    $browseUser['ftp_base_path_decrypted'] = $currentConnection['ftp_base_path_decrypted'];
} else {
    // No connection found - show error
    $error = 'Aucune connexion FTP configurée. Veuillez ajouter une connexion dans votre profil.';
}

$files = [];

if (!$error) {
    try {
        $ftpService = new \App\Services\FTPService($browseUser);
        $ftpService->connect();
        $files = $ftpService->listFiles($path);
        $ftpService->close();

        // Sort: directories first, then files
        usort($files, function($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $b['is_dir'] - $a['is_dir'];
        });

    } catch (Exception $e) {
        $error = 'Erreur de connexion FTP: ' . $e->getMessage();
    }
}

require __DIR__ . '/views/dashboard/browse.php';
