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
$folderPath = $_POST['folder_path'] ?? '';
$folderName = $_POST['folder_name'] ?? 'download';

if (empty($folderPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Folder path is required']);
    exit;
}

try {
    // Create ZIP job
    $zipJobService = new \App\Services\ZipJobService();
    $job = $zipJobService->createJob($user['id'], $folderPath, $folderName);

    // Start ZIP generation in background
    // Use /usr/bin/php instead of PHP_BINARY (which points to php-fpm in web context)
    $phpBinary = '/usr/bin/php';
    $workerScript = __DIR__ . '/zip-worker.php';
    $command = sprintf(
        '%s %s %d > /dev/null 2>&1 &',
        escapeshellarg($phpBinary),
        escapeshellarg($workerScript),
        $job['id']
    );

    exec($command);

    echo json_encode([
        'success' => true,
        'job_id' => $job['id'],
        'token' => $job['token'],
        'message' => 'ZIP generation started'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start ZIP generation: ' . $e->getMessage()]);
}
