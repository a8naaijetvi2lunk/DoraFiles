<?php

/**
 * Background worker for ZIP generation
 * Usage: php zip-worker.php <job_id>
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

if ($argc < 2) {
    die('Usage: php zip-worker.php <job_id>' . PHP_EOL);
}

$jobId = (int) $argv[1];

require_once __DIR__ . '/../vendor/autoload.php';

loadEnv();

// Log helper
function workerLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] ZIP Worker: $message");
}

workerLog("Starting worker for job $jobId");

try {
    $zipJobService = new \App\Services\ZipJobService();

    // Get job details
    $stmt = db()->prepare("SELECT * FROM zip_jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        workerLog("Job $jobId not found");
        exit(1);
    }

    workerLog("Job $jobId found: {$job['folder_name']}");

    // Check if already processing or completed
    if ($job['status'] !== 'pending') {
        workerLog("Job $jobId already has status: {$job['status']}");
        exit(0);
    }

    // Get user data for FTP connection
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$job['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $zipJobService->updateStatus($jobId, 'failed', 'User not found');
        workerLog("User {$job['user_id']} not found");
        exit(1);
    }

    workerLog("User found: {$user['email']}");

    // Prepare user data with FTP credentials
    if (!empty($user['active_ftp_connection_id'])) {
        workerLog("Using active FTP connection: {$user['active_ftp_connection_id']}");
        $ftpService = new \App\Services\FTPConnectionService();
        $activeConn = $ftpService->getConnection($user['active_ftp_connection_id'], $user['id']);

        if ($activeConn) {
            $user['ftp_host_decrypted'] = $activeConn['ftp_host_decrypted'];
            $user['ftp_port_decrypted'] = $activeConn['ftp_port_decrypted'];
            $user['ftp_username_decrypted'] = $activeConn['ftp_username_decrypted'];
            $user['ftp_password_decrypted'] = $activeConn['ftp_password_decrypted'];
            $user['ftp_base_path_decrypted'] = $activeConn['ftp_base_path_decrypted'];
        } else {
            workerLog("Active FTP connection not found, falling back to default");
            $user['ftp_host_decrypted'] = decrypt($user['ftp_host']);
            $user['ftp_port_decrypted'] = decrypt($user['ftp_port']);
            $user['ftp_username_decrypted'] = decrypt($user['ftp_username']);
            $user['ftp_password_decrypted'] = decrypt($user['ftp_password']);
            $user['ftp_base_path_decrypted'] = $user['ftp_base_path'] ? decrypt($user['ftp_base_path']) : '/';
        }
    } else {
        workerLog("Using default FTP credentials");
        $user['ftp_host_decrypted'] = decrypt($user['ftp_host']);
        $user['ftp_port_decrypted'] = decrypt($user['ftp_port']);
        $user['ftp_username_decrypted'] = decrypt($user['ftp_username']);
        $user['ftp_password_decrypted'] = decrypt($user['ftp_password']);
        $user['ftp_base_path_decrypted'] = $user['ftp_base_path'] ? decrypt($user['ftp_base_path']) : '/';
    }

    workerLog("Connecting to FTP: {$user['ftp_host_decrypted']}:{$user['ftp_port_decrypted']}");

    // Create FTP service and generate ZIP
    $ftpService = new \App\Services\FTPService($user);

    workerLog("Starting ZIP generation for: {$job['folder_path']}");

    $result = $ftpService->downloadFolderAsZipAsync(
        $job['folder_path'],
        $job['folder_name'],
        $jobId
    );

    workerLog("Job $jobId completed successfully. ZIP file: {$result['zipFile']}");
    workerLog("ZIP size: " . filesize($result['zipFile']) . " bytes");

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    workerLog("Job $jobId failed: $errorMsg");
    workerLog("Stack trace: " . $e->getTraceAsString());

    if (isset($zipJobService)) {
        $zipJobService->updateStatus($jobId, 'failed', $errorMsg);
    }
    exit(1);
}
