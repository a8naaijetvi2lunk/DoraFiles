<?php

namespace App\Services;

/**
 * Service for FTP file operations
 *
 * Handles file browsing, upload, download, and management
 * operations on FTP servers with security validation.
 */
class FTPService
{
    /** @var resource|null FTP connection resource */
    private $connection = null;

    /** @var array User data with FTP credentials */
    private array $user;

    /** @var int Maximum ZIP size in bytes */
    private int $maxZipSize;

    /** @var int Current ZIP size during generation */
    private int $currentZipSize = 0;

    /** @var int Total files count for progress tracking */
    private int $totalFiles = 0;

    /** @var int Processed files count for progress tracking */
    private int $processedFiles = 0;

    /** @var int|null Job ID for async ZIP generation */
    private ?int $jobId = null;

    /** @var ZipJobService|null Service for tracking ZIP job progress */
    private ?ZipJobService $zipJobService = null;

    /**
     * Create a new FTP service instance
     *
     * @param array $user User data with decrypted FTP credentials
     */
    public function __construct(array $user)
    {
        $this->user = $user;

        // Load max ZIP size from .env (default: 50GB)
        $maxSizeGB = (int) env('MAX_ZIP_SIZE_GB', 50);
        $this->maxZipSize = $maxSizeGB * 1024 * 1024 * 1024;
    }

    /**
     * Connect to FTP server
     *
     * @return bool True on successful connection
     * @throws \Exception If connection or login fails
     */
    public function connect(): bool
    {
        $host = $this->user['ftp_host_decrypted'];
        $port = (int) $this->user['ftp_port_decrypted'];
        $username = $this->user['ftp_username_decrypted'];
        $password = $this->user['ftp_password_decrypted'];

        $timeout = (int) env('FTP_TIMEOUT', 10);

        $this->connection = @ftp_connect($host, $port, $timeout);

        if ($this->connection === false) {
            throw new \Exception("Cannot connect to FTP server: $host:$port");
        }

        $loginResult = @ftp_login($this->connection, $username, $password);

        if ($loginResult === false) {
            @ftp_close($this->connection);
            $this->connection = null;
            throw new \Exception("FTP login failed for user: $username");
        }

        // Enable passive mode (try both passive and active mode for compatibility)
        $passiveMode = @ftp_pasv($this->connection, true);
        if (!$passiveMode) {
            @ftp_pasv($this->connection, false);
        }

        return true;
    }

    /**
     * Build full FTP path from base path and relative path
     *
     * @param string $relativePath Relative path from user's base
     * @return string Full FTP path
     */
    private function buildFullPath(string $relativePath): string
    {
        $basePath = $this->user['ftp_base_path_decrypted'] ?? '/';

        $basePath = trim($basePath);
        if (empty($basePath)) {
            $basePath = '/';
        }

        $relativePath = trim($relativePath);
        if (empty($relativePath) || $relativePath === '.') {
            $relativePath = '/';
        }

        if ($basePath === '/') {
            return '/' . ltrim($relativePath, '/');
        }

        return rtrim($basePath, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * List files in a directory
     *
     * @param string $directory Directory path relative to user's base
     * @return array List of file/directory information
     */
    public function listFiles(string $directory = '/'): array
    {
        if (!$this->connection) {
            $this->connect();
        }

        $directory = \App\Security\SecurityMiddleware::validatePath($directory);
        $fullPath = $this->buildFullPath($directory);

        $files = [];
        $rawList = @ftp_rawlist($this->connection, $fullPath);

        if ($rawList === false) {
            return [];
        }

        foreach ($rawList as $item) {
            $parsed = $this->parseRawListItem($item);
            if ($parsed && $parsed['name'] !== '.' && $parsed['name'] !== '..') {
                $parsed['path'] = rtrim($directory, '/') . '/' . $parsed['name'];
                $files[] = $parsed;
            }
        }

        return $files;
    }

    /**
     * Parse FTP raw list item into structured data
     *
     * @param string $item Raw list item string
     * @return array|null Parsed file info or null if invalid
     */
    private function parseRawListItem(string $item): ?array
    {
        $parts = preg_split('/\s+/', $item, 9);

        if (count($parts) < 9) {
            return null;
        }

        $isDir = substr($parts[0], 0, 1) === 'd';

        return [
            'type' => $isDir ? 'dir' : 'file',
            'permissions' => $parts[0],
            'size' => (int) $parts[4],
            'date' => $parts[5] . ' ' . $parts[6] . ' ' . $parts[7],
            'name' => $parts[8],
            'is_dir' => $isDir
        ];
    }

    /**
     * Get file size
     *
     * @param string $filePath File path relative to user's base
     * @return int File size in bytes, or -1 if not found
     */
    public function getFileSize(string $filePath): int
    {
        if (!$this->connection) {
            $this->connect();
        }

        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);
        $fullPath = $this->buildFullPath($filePath);

        return @ftp_size($this->connection, $fullPath);
    }

    /**
     * Stream file directly to output
     *
     * @param string $filePath File path relative to user's base
     * @return void
     * @throws \Exception If download fails
     */
    public function streamFile(string $filePath): void
    {
        if (!$this->connection) {
            $this->connect();
        }

        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);
        $fullPath = $this->buildFullPath($filePath);

        $tempStream = fopen('php://output', 'w');

        if (!@ftp_fget($this->connection, $tempStream, $fullPath, FTP_BINARY)) {
            fclose($tempStream);
            throw new \Exception("Failed to download file");
        }

        fclose($tempStream);
    }

    /**
     * Download file to a temporary location
     *
     * @param string $filePath File path relative to user's base
     * @return string Path to temporary file
     * @throws \Exception If download fails
     */
    public function downloadToTemp(string $filePath): string
    {
        if (!$this->connection) {
            $this->connect();
        }

        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);
        $fullPath = $this->buildFullPath($filePath);

        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

        if (!@ftp_get($this->connection, $tempFile, $fullPath, FTP_BINARY)) {
            @unlink($tempFile);
            throw new \Exception("Failed to download file");
        }

        return $tempFile;
    }

    /**
     * Download folder as ZIP asynchronously with progress tracking
     *
     * @param string $folderPath FTP folder path relative to user's base
     * @param string $folderName Display name for the folder
     * @param int $jobId ZIP job ID for progress tracking
     * @return array Array with 'zipFile' path and 'tempFiles' to cleanup
     * @throws \Exception If ZIP creation fails
     */
    public function downloadFolderAsZipAsync(string $folderPath, string $folderName, int $jobId): array
    {
        $this->jobId = $jobId;
        $this->zipJobService = new ZipJobService();

        if (!$this->connection) {
            $this->connect();
        }

        $folderPath = \App\Security\SecurityMiddleware::validatePath($folderPath);
        $folderName = \App\Security\SecurityMiddleware::sanitizeString($folderName, 255);
        $fullPath = $this->buildFullPath($folderPath);

        // Reset counters
        $this->currentZipSize = 0;
        $this->processedFiles = 0;

        try {
            $this->zipJobService->updateStatus($jobId, 'processing');

            // First pass: count total files and size
            $this->totalFiles = 0;
            $totalSize = 0;
            $this->countFilesInFolder($fullPath, $this->totalFiles, $totalSize);

            $this->zipJobService->updateProgress($jobId, 0, $this->totalFiles, 0, $totalSize);

            // Create temp directory
            $tempDir = sys_get_temp_dir() . '/ftp_zip_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Create ZIP file
            $zipFile = sys_get_temp_dir() . '/zip_job_' . $jobId . '_' . time() . '.zip';
            $zip = new \ZipArchive();

            if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
                $this->rrmdir($tempDir);
                throw new \Exception("Cannot create ZIP file");
            }

            $largeTempFiles = [];

            // Download all files recursively with progress tracking
            $this->addFolderToZipWithProgress($zip, $fullPath, $folderName, $largeTempFiles, $totalSize);

            if (!$zip->close()) {
                throw new \Exception("Failed to finalize ZIP archive. Disk may be full.");
            }

            if (!file_exists($zipFile) || filesize($zipFile) === 0) {
                throw new \Exception("ZIP file was not created properly. Disk space may be insufficient.");
            }

            // Final progress update
            $this->zipJobService->updateProgress(
                $jobId,
                $this->processedFiles,
                $this->totalFiles,
                $this->currentZipSize,
                $totalSize
            );

            // Cleanup
            $this->rrmdir($tempDir);
            foreach ($largeTempFiles as $tempFile) {
                @unlink($tempFile);
            }

            $this->zipJobService->markCompleted($jobId, $zipFile);

            return ['zipFile' => $zipFile, 'tempFiles' => []];

        } catch (\Exception $e) {
            $this->zipJobService->updateStatus($jobId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count files and total size in folder recursively
     *
     * @param string $ftpPath Full FTP path
     * @param int &$count Reference to file counter
     * @param int &$totalSize Reference to size counter
     * @return void
     */
    private function countFilesInFolder(string $ftpPath, int &$count, int &$totalSize): void
    {
        $files = @ftp_rawlist($this->connection, $ftpPath);

        if ($files === false) {
            return;
        }

        foreach ($files as $item) {
            $parsed = $this->parseRawListItem($item);

            if (!$parsed || $parsed['name'] === '.' || $parsed['name'] === '..') {
                continue;
            }

            $itemPath = rtrim($ftpPath, '/') . '/' . $parsed['name'];

            if ($parsed['is_dir']) {
                $this->countFilesInFolder($itemPath, $count, $totalSize);
            } else {
                $count++;
                $totalSize += $parsed['size'];
            }
        }
    }

    /**
     * Add folder contents to ZIP with progress tracking
     *
     * @param \ZipArchive $zip ZIP archive instance
     * @param string $ftpPath Full FTP path
     * @param string $zipPath Path within ZIP archive
     * @param array &$largeTempFiles Reference to large temp files for cleanup
     * @param int $totalSize Total size for progress calculation
     * @return void
     */
    private function addFolderToZipWithProgress(\ZipArchive $zip, string $ftpPath, string $zipPath = '', array &$largeTempFiles = [], int $totalSize = 0): void
    {
        set_time_limit(300);

        $files = @ftp_rawlist($this->connection, $ftpPath);

        if ($files === false) {
            error_log("FTP rawlist failed for: $ftpPath");
            return;
        }

        foreach ($files as $item) {
            $parsed = $this->parseRawListItem($item);

            if (!$parsed || $parsed['name'] === '.' || $parsed['name'] === '..') {
                continue;
            }

            $itemFtpPath = rtrim($ftpPath, '/') . '/' . $parsed['name'];
            $itemZipPath = rtrim($zipPath, '/') . '/' . $parsed['name'];

            if ($parsed['is_dir']) {
                $zip->addEmptyDir($itemZipPath);
                $this->addFolderToZipWithProgress($zip, $itemFtpPath, $itemZipPath, $largeTempFiles, $totalSize);
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

                if (@ftp_get($this->connection, $tempFile, $itemFtpPath, FTP_BINARY)) {
                    $fileSize = filesize($tempFile);

                    // Check ZIP size limit
                    $this->currentZipSize += $fileSize;
                    if ($this->currentZipSize > $this->maxZipSize) {
                        @unlink($tempFile);
                        $maxSizeGB = env('MAX_ZIP_SIZE_GB', 50);
                        throw new \Exception("ZIP size limit exceeded ({$maxSizeGB}GB maximum). Please download smaller folders or individual files.");
                    }

                    // Use addFromString for small files, addFile for large ones
                    if ($fileSize < 20 * 1024 * 1024) {
                        $content = file_get_contents($tempFile);
                        $zip->addFromString($itemZipPath, $content);
                        @unlink($tempFile);
                        unset($content);
                    } else {
                        $zip->addFile($tempFile, $itemZipPath);
                        $largeTempFiles[] = $tempFile;
                    }

                    // Update progress
                    $this->processedFiles++;

                    if ($this->jobId && $this->zipJobService) {
                        $shouldUpdate = false;

                        if ($this->totalFiles <= 10) {
                            $shouldUpdate = true;
                        } elseif ($this->processedFiles % 20 === 0 || $this->currentZipSize % (100 * 1024 * 1024) === 0) {
                            $shouldUpdate = true;
                        } elseif ($this->processedFiles === $this->totalFiles) {
                            $shouldUpdate = true;
                        }

                        if ($shouldUpdate) {
                            $this->zipJobService->updateProgress(
                                $this->jobId,
                                $this->processedFiles,
                                $this->totalFiles,
                                $this->currentZipSize,
                                $totalSize
                            );
                        }
                    }
                } else {
                    @unlink($tempFile);
                }
            }
        }
    }

    /**
     * Recursively remove directory
     *
     * @param string $dir Directory path to remove
     * @return void
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Check if file exists on FTP server
     *
     * @param string $filePath File path relative to user's base
     * @return bool True if file exists
     */
    public function fileExists(string $filePath): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);
        $fullPath = $this->buildFullPath($filePath);

        $size = @ftp_size($this->connection, $fullPath);
        return $size !== -1;
    }

    /**
     * Upload file to FTP server
     *
     * @param string $localFilePath Path to local file
     * @param string $remotePath Remote directory path
     * @param string $fileName Remote file name
     * @return bool True on success
     * @throws \Exception If upload fails
     */
    public function uploadFile(string $localFilePath, string $remotePath, string $fileName): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $remotePath = \App\Security\SecurityMiddleware::validatePath($remotePath);
        $fileName = \App\Security\SecurityMiddleware::sanitizeString($fileName, 255);

        // Prevent null bytes and dangerous characters
        if (strpos($fileName, "\0") !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
            throw new \Exception("Invalid file name");
        }

        $remotePath = rtrim($remotePath, '/') . '/' . $fileName;
        $fullRemotePath = $this->buildFullPath($remotePath);

        $fileSize = filesize($localFilePath);

        // For large files, use non-blocking upload
        if ($fileSize > 100 * 1024 * 1024) {
            $ret = @ftp_nb_put($this->connection, $fullRemotePath, $localFilePath, FTP_BINARY);

            while ($ret === FTP_MOREDATA) {
                set_time_limit(300);
                $ret = @ftp_nb_continue($this->connection);
            }

            if ($ret !== FTP_FINISHED) {
                throw new \Exception("Failed to upload file");
            }
        } else {
            if (!@ftp_put($this->connection, $fullRemotePath, $localFilePath, FTP_BINARY)) {
                throw new \Exception("Failed to upload file");
            }
        }

        return true;
    }

    /**
     * Create directory on FTP server
     *
     * @param string $path Directory path relative to user's base
     * @return bool True on success
     * @throws \Exception If creation fails
     */
    public function createDirectory(string $path): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $path = \App\Security\SecurityMiddleware::validatePath($path);
        $fullPath = $this->buildFullPath($path);

        if (!@ftp_mkdir($this->connection, $fullPath)) {
            throw new \Exception("Failed to create directory");
        }

        return true;
    }

    /**
     * Delete file from FTP server
     *
     * @param string $filePath File path relative to user's base
     * @return bool True on success
     * @throws \Exception If deletion fails
     */
    public function deleteFile(string $filePath): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);
        $fullPath = $this->buildFullPath($filePath);

        if (!@ftp_delete($this->connection, $fullPath)) {
            throw new \Exception("Failed to delete file");
        }

        return true;
    }

    /**
     * Move or rename file/directory on FTP server
     *
     * @param string $sourcePath Source path relative to user's base
     * @param string $destinationPath Destination path relative to user's base
     * @return bool True on success
     * @throws \Exception If move fails
     */
    public function moveItem(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $sourcePath = \App\Security\SecurityMiddleware::validatePath($sourcePath);
        $destinationPath = \App\Security\SecurityMiddleware::validatePath($destinationPath);

        $fullSourcePath = $this->buildFullPath($sourcePath);
        $fullDestPath = $this->buildFullPath($destinationPath);

        // Ensure connection is active
        if (!@ftp_pwd($this->connection)) {
            $this->connect();
        }

        // Check if source exists
        $sourceSize = @ftp_size($this->connection, $fullSourcePath);
        if ($sourceSize === -1) {
            $currentDir = @ftp_pwd($this->connection);
            if (!@ftp_chdir($this->connection, $fullSourcePath)) {
                throw new \Exception("Source path does not exist: '$fullSourcePath'");
            }
            @ftp_chdir($this->connection, $currentDir);
        }

        // Check destination parent exists
        $destParent = dirname($fullDestPath);
        if ($destParent !== '/' && $destParent !== '.') {
            $currentDir = @ftp_pwd($this->connection);
            if (!@ftp_chdir($this->connection, $destParent)) {
                throw new \Exception("Destination directory does not exist: '$destParent'");
            }
            @ftp_chdir($this->connection, $currentDir);
        }

        if (!@ftp_rename($this->connection, $fullSourcePath, $fullDestPath)) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            throw new \Exception("Failed to move item: $errorMsg. Check FTP user permissions.");
        }

        return true;
    }

    /**
     * Delete directory recursively from FTP server
     *
     * @param string $dirPath Directory path relative to user's base
     * @return bool True on success
     * @throws \Exception If deletion fails
     */
    public function deleteDirectory(string $dirPath): bool
    {
        if (!$this->connection) {
            $this->connect();
        }

        $dirPath = \App\Security\SecurityMiddleware::validatePath($dirPath);
        $fullPath = $this->buildFullPath($dirPath);

        $files = @ftp_rawlist($this->connection, $fullPath);

        if ($files !== false) {
            foreach ($files as $item) {
                $parsed = $this->parseRawListItem($item);

                if (!$parsed || $parsed['name'] === '.' || $parsed['name'] === '..') {
                    continue;
                }

                $itemPath = rtrim($fullPath, '/') . '/' . $parsed['name'];

                if ($parsed['is_dir']) {
                    $basePath = $this->user['ftp_base_path_decrypted'] ?? '/';
                    $basePath = rtrim($basePath, '/');
                    $relativePath = ltrim(str_replace($basePath, '', $itemPath), '/');
                    $this->deleteDirectory($relativePath);
                } else {
                    @ftp_delete($this->connection, $itemPath);
                }
            }
        }

        if (!@ftp_rmdir($this->connection, $fullPath)) {
            throw new \Exception("Failed to delete directory");
        }

        return true;
    }

    /**
     * Close FTP connection
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->close();
    }
}
