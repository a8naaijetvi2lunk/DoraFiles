<?php

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();
require_once __DIR__ . '/../app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$token = $_POST['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token is required']);
    exit;
}

try {
    $user = auth();
    $zipJobService = new \App\Services\ZipJobService();

    // Get job by token and verify ownership
    $job = $zipJobService->getJob($token, $user['id']);

    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }

    // Only start worker if job is pending
    if ($job['status'] !== 'pending') {
        echo json_encode([
            'success' => true,
            'message' => 'Worker already started or job completed',
            'status' => $job['status']
        ]);
        exit;
    }

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
        'message' => 'Worker started successfully',
        'job_id' => $job['id']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to start worker: ' . $e->getMessage()]);
}
