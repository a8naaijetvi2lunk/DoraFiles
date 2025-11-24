<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Increase execution time for large ZIP files
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '1G');

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

// Verify CSRF
if (!isset($_GET['csrf']) || !csrf_verify($_GET['csrf'])) {
    http_response_code(403);
    die('Invalid CSRF token');
}

// Get parameters
$path = $_GET['path'] ?? '';
$name = $_GET['name'] ?? 'download';
$type = $_GET['type'] ?? 'file';

if (empty($path)) {
    http_response_code(400);
    die('Missing path parameter');
}

try {
    $ftpUser = getFTPUserData();
    $ftpService = new \App\Services\FTPService($ftpUser);
    $ftpService->connect();

    if ($type === 'dir') {
        // Create async ZIP job and redirect to progress page
        $user = $_SESSION['user'];
        $zipJobService = new \App\Services\ZipJobService();

        $job = $zipJobService->createJob(
            $user['id'],
            $path,
            $name
        );

        // Start ZIP generation in background immediately
        // Use /usr/bin/php instead of PHP_BINARY (which points to php-fpm in web context)
        // Allocate 2GB memory for large video files
        $phpBinary = '/usr/bin/php';
        $workerScript = __DIR__ . '/api/zip-worker.php';
        $logFile = __DIR__ . '/storage/logs/zip-worker.log';

        // SECURITY FIX: Cast job ID to integer to prevent command injection
        $jobId = (int)$job['id'];
        if ($jobId <= 0) {
            throw new \Exception("Invalid job ID");
        }

        $command = sprintf(
            '%s -d memory_limit=2G %s %d >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($workerScript),
            $jobId,
            escapeshellarg($logFile)
        );

        exec($command);

        // Redirect to progress page
        header('Location: /zip-progress.php?token=' . $job['token']);
        exit;

    } else {
        // Single file - direct download
        $fileSize = $ftpService->getFileSize($path);

        if ($fileSize === -1) {
            throw new \Exception("File not found");
        }

        // SECURITY FIX: Properly sanitize filename to prevent header injection
        $safeName = preg_replace('/[^\w\s\-\.\(\)\[\]]/', '_', $name);
        $safeName = preg_replace('/[\r\n]/', '', $safeName); // Remove newlines

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . $fileSize);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $ftpService->streamFile($path);
    }

    $ftpService->close();

} catch (Exception $e) {
    http_response_code(500);
    die('Download error: ' . $e->getMessage());
}
