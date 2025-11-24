<?php

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();
require_once __DIR__ . '/../app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = auth();
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Token is required']);
    exit;
}

try {
    $zipJobService = new \App\Services\ZipJobService();
    $job = $zipJobService->getJob($token, $user['id']);

    if (!$job) {
        http_response_code(404);
        echo 'ZIP job not found';
        exit;
    }

    if ($job['status'] !== 'completed') {
        http_response_code(400);
        echo 'ZIP is not ready yet. Current status: ' . $job['status'];
        exit;
    }

    if (!$job['zip_file_path'] || !file_exists($job['zip_file_path'])) {
        http_response_code(404);
        echo 'ZIP file not found on server';
        exit;
    }

    $zipFile = $job['zip_file_path'];
    $fileName = $job['folder_name'] . '.zip';

    // Set headers for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($zipFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Stream the file
    $handle = fopen($zipFile, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }

    // Note: Don't delete the ZIP file immediately after download
    // It will be cleaned up by the cleanup job after expiration

} catch (Exception $e) {
    http_response_code(500);
    echo 'Failed to download ZIP: ' . $e->getMessage();
}
