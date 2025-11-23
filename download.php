<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security headers
\App\Security\SecurityMiddleware::applySecurityHeaders();

// Get token from URL or POST
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$token = null;

// Parse token from /dl/{token}, GET or POST
if (preg_match('#/dl/([a-f0-9]+)#', $requestUri, $matches)) {
    $token = $matches[1];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
}

// Validate token
if (!$token || !\App\Security\SecurityMiddleware::validateToken($token)) {
    \App\Security\SecurityMiddleware::logSecurityEvent('INVALID_DOWNLOAD_TOKEN_ACCESS', ['token' => $token ? substr($token, 0, 10) : 'empty']);
    http_response_code(404);
    die('Invalid link');
}

$linkService = new \App\Services\LinkService();
$link = $linkService->getLinkByToken($token);

if (!$link) {
    http_response_code(404);
    $error = 'Ce lien n\'existe pas ou a expiré';
    require __DIR__ . '/views/download.php';
    exit;
}

// Check if password is required
if (!empty($link['password_hash'])) {
    $password = $_POST['password'] ?? '';

    if (empty($password)) {
        // Show password form
        $fileName = $link['file_name'];
        require __DIR__ . '/views/password-required.php';
        exit;
    }

    // Verify password
    if (!$linkService->verifyPassword($link, $password)) {
        $error = 'Mot de passe incorrect';
        $fileName = $link['file_name'];
        require __DIR__ . '/views/password-required.php';
        exit;
    }
}

// If not confirmed, show download page
if (!isset($_POST['confirm'])) {
    $fileName = $link['file_name'];
    $fileSize = $link['file_size'];
    $expiresAt = $link['expires_at'];
    $downloadCount = $link['download_count'];
    $token = $link['token'];

    require __DIR__ . '/views/download.php';
    exit;
}

// Check rate limiting before actual download
$rateLimitService = new \App\Services\RateLimitService();
$clientIP = \App\Services\RateLimitService::getClientIP();

if (!$rateLimitService->check($clientIP, 'download')) {
    http_response_code(429);
    $error = 'Trop de téléchargements. Veuillez réessayer dans quelques minutes.';
    require __DIR__ . '/views/download.php';
    exit;
}

// Stream the file
try {
    // Prepare user data with decrypted FTP credentials from the shared link
    $ftpUser = [
        'ftp_host_decrypted' => decrypt($link['ftp_host']),
        'ftp_port_decrypted' => decrypt($link['ftp_port']),
        'ftp_username_decrypted' => decrypt($link['ftp_username']),
        'ftp_password_decrypted' => decrypt($link['ftp_password']),
        'ftp_base_path_decrypted' => $link['ftp_base_path'] ? decrypt($link['ftp_base_path']) : '/',
    ];

    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    // Verify file still exists
    if (!$ftpService->fileExists($link['file_path'])) {
        $error = 'Le fichier n\'existe plus sur le serveur';
        require __DIR__ . '/views/download.php';
        exit;
    }

    // Increment download count
    $linkService->incrementDownload($link['id']);

    // Set security headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($link['file_name']) . '"');
    header('Content-Length: ' . $link['file_size']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Stream the file
    $ftpService->streamFile($link['file_path']);
    $ftpService->close();

    exit;

} catch (Exception $e) {
    http_response_code(500);
    $error = 'Erreur lors du téléchargement: ' . $e->getMessage();
    require __DIR__ . '/views/download.php';
    exit;
}
