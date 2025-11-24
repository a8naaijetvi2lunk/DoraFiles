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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth();
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token is required']);
    exit;
}

try {
    $zipJobService = new \App\Services\ZipJobService();
    $job = $zipJobService->getJob($token, $user['id']);

    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }

    // Calculate human-readable time remaining
    $timeRemaining = null;
    if ($job['estimated_time_remaining']) {
        $seconds = $job['estimated_time_remaining'];
        if ($seconds < 60) {
            $timeRemaining = $seconds . 's';
        } elseif ($seconds < 3600) {
            $timeRemaining = floor($seconds / 60) . 'min ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $timeRemaining = $hours . 'h ' . $minutes . 'min';
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $job['status'],
        'progress_percent' => (int) $job['progress_percent'],
        'processed_files' => (int) $job['processed_files'],
        'total_files' => (int) $job['total_files'],
        'processed_size' => formatBytes($job['processed_size_bytes']),
        'total_size' => formatBytes($job['total_size_bytes']),
        'time_remaining' => $timeRemaining,
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'started_at' => $job['started_at'],
        'completed_at' => $job['completed_at']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to get job status: ' . $e->getMessage()]);
}
