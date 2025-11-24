<?php

namespace App\Services;

/**
 * Service for managing asynchronous ZIP generation jobs
 *
 * Handles creation, progress tracking, and cleanup of ZIP jobs
 * for folder downloads.
 */
class ZipJobService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Create a new ZIP generation job
     *
     * @param int $userId User ID
     * @param string $folderPath Path to the folder to ZIP
     * @param string $folderName Display name for the folder
     * @return array Job data with 'id' and 'token'
     */
    public function createJob(int $userId, string $folderPath, string $folderName): array
    {
        $token = generateToken(32);

        $stmt = $this->pdo->prepare("
            INSERT INTO zip_jobs (user_id, token, folder_path, folder_name, status, expires_at)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");

        $stmt->execute([$userId, $token, $folderPath, $folderName]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'token' => $token
        ];
    }

    /**
     * Get job by token
     *
     * @param string $token Job token
     * @param int|null $userId Optional user ID for authorization
     * @return array|false Job data or false if not found
     */
    public function getJob(string $token, ?int $userId = null): array|false
    {
        $sql = "SELECT * FROM zip_jobs WHERE token = ?";
        $params = [$token];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    /**
     * Update job status
     *
     * @param int $jobId Job ID
     * @param string $status New status (pending, processing, completed, failed)
     * @param string|null $errorMessage Error message for failed jobs
     * @return void
     */
    public function updateStatus(int $jobId, string $status, ?string $errorMessage = null): void
    {
        $updates = ['status = ?'];
        $params = [$status];

        if ($status === 'processing' && !$this->hasStartTime($jobId)) {
            $updates[] = 'started_at = NOW()';
        }

        if ($status === 'completed' || $status === 'failed') {
            $updates[] = 'completed_at = NOW()';
        }

        if ($errorMessage !== null) {
            $updates[] = 'error_message = ?';
            $params[] = $errorMessage;
        }

        $params[] = $jobId;

        $sql = "UPDATE zip_jobs SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Update job progress
     *
     * @param int $jobId Job ID
     * @param int $processedFiles Number of files processed
     * @param int $totalFiles Total number of files
     * @param int $processedBytes Bytes processed
     * @param int $totalBytes Total bytes
     * @return void
     */
    public function updateProgress(int $jobId, int $processedFiles, int $totalFiles, int $processedBytes, int $totalBytes): void
    {
        $progressPercent = $totalFiles > 0 ? (int) (($processedFiles / $totalFiles) * 100) : 0;

        // Calculate estimated time remaining based on bytes processed
        $estimatedTime = null;
        $job = $this->getJobById($jobId);

        if ($job && $job['started_at'] && $processedBytes > 0) {
            $elapsedSeconds = time() - strtotime($job['started_at']);
            $bytesPerSecond = $processedBytes / max($elapsedSeconds, 1);
            $remainingBytes = $totalBytes - $processedBytes;
            $estimatedTime = $bytesPerSecond > 0 ? (int) ($remainingBytes / $bytesPerSecond) : null;
        }

        $stmt = $this->pdo->prepare("
            UPDATE zip_jobs
            SET progress_percent = ?,
                processed_files = ?,
                total_files = ?,
                processed_size_bytes = ?,
                total_size_bytes = ?,
                estimated_time_remaining = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $progressPercent,
            $processedFiles,
            $totalFiles,
            $processedBytes,
            $totalBytes,
            $estimatedTime,
            $jobId
        ]);
    }

    /**
     * Mark job as completed with ZIP file path
     *
     * @param int $jobId Job ID
     * @param string $zipFilePath Path to the generated ZIP file
     * @return void
     */
    public function markCompleted(int $jobId, string $zipFilePath): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE zip_jobs
            SET status = 'completed',
                zip_file_path = ?,
                progress_percent = 100,
                completed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$zipFilePath, $jobId]);
    }

    /**
     * Get job by ID (internal use)
     *
     * @param int $jobId Job ID
     * @return array|false Job data or false if not found
     */
    private function getJobById(int $jobId): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM zip_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        return $stmt->fetch();
    }

    /**
     * Check if job has a start time
     *
     * @param int $jobId Job ID
     * @return bool
     */
    private function hasStartTime(int $jobId): bool
    {
        $stmt = $this->pdo->prepare("SELECT started_at FROM zip_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $result = $stmt->fetch();
        return $result && $result['started_at'] !== null;
    }

    /**
     * Get user's recent jobs
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of jobs to return
     * @return array List of jobs
     */
    public function getUserJobs(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM zip_jobs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Clean up expired jobs and their ZIP files
     *
     * @return int Number of deleted jobs
     */
    public function cleanupExpiredJobs(): int
    {
        // Get expired jobs with ZIP files to delete
        $stmt = $this->pdo->prepare("
            SELECT id, zip_file_path
            FROM zip_jobs
            WHERE expires_at < NOW()
            AND zip_file_path IS NOT NULL
        ");

        $stmt->execute();
        $expiredJobs = $stmt->fetchAll();

        // Delete ZIP files
        foreach ($expiredJobs as $job) {
            if ($job['zip_file_path'] && file_exists($job['zip_file_path'])) {
                @unlink($job['zip_file_path']);
            }
        }

        // Delete expired jobs from database
        $deleteStmt = $this->pdo->prepare("DELETE FROM zip_jobs WHERE expires_at < NOW()");
        $deleteStmt->execute();

        return count($expiredJobs);
    }
}
