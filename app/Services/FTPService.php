<?php

namespace App\Services;

class FTPService {
    private $connection;
    private $user;

    public function __construct($user) {
        $this->user = $user;
    }

    /**
     * Connect to FTP server
     */
    public function connect() {
        $host = $this->user['ftp_host_decrypted'];
        $port = (int) $this->user['ftp_port_decrypted'];
        $username = $this->user['ftp_username_decrypted'];
        $password = $this->user['ftp_password_decrypted'];

        $timeout = (int) env('FTP_TIMEOUT', 10);

        // Try regular FTP (more compatible)
        $this->connection = @ftp_connect($host, $port, $timeout);

        if ($this->connection === false) {
            throw new \Exception("Cannot connect to FTP server: $host:$port");
        }

        // Try to login
        $loginResult = @ftp_login($this->connection, $username, $password);

        if ($loginResult === false) {
            $error = error_get_last();
            @ftp_close($this->connection);
            $this->connection = null;
            throw new \Exception("FTP login failed for user: $username");
        }

        // Enable passive mode (try both passive and active mode for compatibility)
        $passiveMode = @ftp_pasv($this->connection, true);
        if (!$passiveMode) {
            // If passive mode fails, try active mode
            @ftp_pasv($this->connection, false);
        }

        return true;
    }

    /**
     * Build full FTP path from base path and relative path
     *
     * @param string $relativePath
     * @return string
     */
    private function buildFullPath($relativePath) {
        $basePath = $this->user['ftp_base_path_decrypted'] ?? '/';

        // Normalize base path
        $basePath = trim($basePath);
        if (empty($basePath)) {
            $basePath = '/';
        }

        // Normalize relative path
        $relativePath = trim($relativePath);
        if (empty($relativePath) || $relativePath === '.') {
            $relativePath = '/';
        }

        // Build full path
        if ($basePath === '/') {
            return '/' . ltrim($relativePath, '/');
        } else {
            return rtrim($basePath, '/') . '/' . ltrim($relativePath, '/');
        }
    }

    /**
     * List files in directory
     */
    public function listFiles($directory = '/') {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate and sanitize directory path
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
     * Parse FTP raw list item
     */
    private function parseRawListItem($item) {
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
     */
    public function getFileSize($filePath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);

        $fullPath = $this->buildFullPath($filePath);

        return @ftp_size($this->connection, $fullPath);
    }

    /**
     * Download file and stream to output
     */
    public function streamFile($filePath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);

        $fullPath = $this->buildFullPath($filePath);

        // Create temporary stream
        $tempStream = fopen('php://output', 'w');

        if (!@ftp_fget($this->connection, $tempStream, $fullPath, FTP_BINARY)) {
            fclose($tempStream);
            throw new \Exception("Failed to download file");
        }

        fclose($tempStream);
    }

    /**
     * Download file to a temporary location
     */
    public function downloadToTemp($filePath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
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
     * Download folder as ZIP
     */
    public function downloadFolderAsZip($folderPath, $folderName) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate paths
        $folderPath = \App\Security\SecurityMiddleware::validatePath($folderPath);
        $folderName = \App\Security\SecurityMiddleware::sanitizeString($folderName, 255);

        $fullPath = $this->buildFullPath($folderPath);

        // Create temp directory for tracking
        $tempDir = sys_get_temp_dir() . '/ftp_zip_' . uniqid();
        mkdir($tempDir, 0755, true);

        // Create ZIP file
        $zipFile = tempnam(sys_get_temp_dir(), 'zip_') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            $this->rrmdir($tempDir);
            throw new \Exception("Cannot create ZIP file");
        }

        // Track large temp files that need to stay until after ZIP is used
        $largeTempFiles = [];

        try {
            // Download all files recursively
            $this->addFolderToZip($zip, $fullPath, $folderName, $largeTempFiles);
            $zip->close();
        } catch (\Exception $e) {
            $zip->close();
            // Clean up temp files on error
            foreach ($largeTempFiles as $tempFile) {
                @unlink($tempFile);
            }
            $this->rrmdir($tempDir);
            @unlink($zipFile);
            throw $e;
        }

        // Clean up temp directory
        $this->rrmdir($tempDir);

        // Note: $largeTempFiles will be cleaned up in download-direct.php AFTER sending the ZIP
        // Store them in the ZIP file for later cleanup
        return ['zipFile' => $zipFile, 'tempFiles' => $largeTempFiles];
    }

    /**
     * Recursively add folder contents to ZIP
     */
    private function addFolderToZip($zip, $ftpPath, $zipPath = '', &$largeTempFiles = []) {
        // Reset time limit for each directory
        set_time_limit(300);

        $files = @ftp_rawlist($this->connection, $ftpPath);

        if ($files === false) {
            error_log("FTP rawlist failed for: $ftpPath");
            return;
        }

        error_log("Processing directory: $ftpPath with " . count($files) . " items");

        foreach ($files as $item) {
            $parsed = $this->parseRawListItem($item);

            if (!$parsed || $parsed['name'] === '.' || $parsed['name'] === '..') {
                continue;
            }

            error_log("Processing item: " . $parsed['name'] . " (type: " . ($parsed['is_dir'] ? 'dir' : 'file') . ")");

            $itemFtpPath = rtrim($ftpPath, '/') . '/' . $parsed['name'];
            $itemZipPath = rtrim($zipPath, '/') . '/' . $parsed['name'];

            if ($parsed['is_dir']) {
                // Add empty directory
                $zip->addEmptyDir($itemZipPath);
                // Recursively add subdirectory contents
                $this->addFolderToZip($zip, $itemFtpPath, $itemZipPath, $largeTempFiles);
            } else {
                // Download file to temp and add to ZIP
                $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

                if (@ftp_get($this->connection, $tempFile, $itemFtpPath, FTP_BINARY)) {
                    $fileSize = filesize($tempFile);

                    // For files under 500MB, use addFromString (loads in memory, faster)
                    if ($fileSize < 500 * 1024 * 1024) {
                        $content = file_get_contents($tempFile);
                        $zip->addFromString($itemZipPath, $content);
                        @unlink($tempFile);
                        unset($content); // Free memory
                    } else {
                        // For very large files (>500MB), use addFile (keeps reference)
                        // This avoids loading entire file in memory
                        $zip->addFile($tempFile, $itemZipPath);
                        // Track this file for cleanup AFTER ZIP is sent to user
                        $largeTempFiles[] = $tempFile;
                    }
                } else {
                    // Clean up failed temp file
                    @unlink($tempFile);
                }
            }
        }
    }

    /**
     * Recursively remove directory
     */
    private function rrmdir($dir) {
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
     * Check if file exists
     */
    public function fileExists($filePath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);

        $fullPath = $this->buildFullPath($filePath);

        $size = @ftp_size($this->connection, $fullPath);
        return $size !== -1;
    }

    /**
     * Upload file to FTP server
     */
    public function uploadFile($localFilePath, $remotePath, $fileName) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate paths
        $remotePath = \App\Security\SecurityMiddleware::validatePath($remotePath);
        $fileName = \App\Security\SecurityMiddleware::sanitizeString($fileName, 255);

        // Prevent null bytes and dangerous characters in filename
        if (strpos($fileName, "\0") !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
            throw new \Exception("Invalid file name");
        }

        $remotePath = rtrim($remotePath, '/') . '/' . $fileName;
        $fullRemotePath = $this->buildFullPath($remotePath);

        // Get file size to determine upload method
        $fileSize = filesize($localFilePath);

        // For files larger than 100MB, use non-blocking upload with timeout reset
        if ($fileSize > 100 * 1024 * 1024) {
            $ret = @ftp_nb_put($this->connection, $fullRemotePath, $localFilePath, FTP_BINARY);

            while ($ret === FTP_MOREDATA) {
                // Reset timeout to prevent PHP script timeout
                set_time_limit(300); // Reset to 5 minutes
                $ret = @ftp_nb_continue($this->connection);
            }

            if ($ret !== FTP_FINISHED) {
                throw new \Exception("Failed to upload file");
            }
        } else {
            // For smaller files, use standard blocking upload
            if (!@ftp_put($this->connection, $fullRemotePath, $localFilePath, FTP_BINARY)) {
                throw new \Exception("Failed to upload file");
            }
        }

        return true;
    }

    /**
     * Create directory on FTP server
     */
    public function createDirectory($path) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $path = \App\Security\SecurityMiddleware::validatePath($path);

        $fullPath = $this->buildFullPath($path);

        if (!@ftp_mkdir($this->connection, $fullPath)) {
            throw new \Exception("Failed to create directory");
        }

        return true;
    }

    /**
     * Delete file from FTP server
     */
    public function deleteFile($filePath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $filePath = \App\Security\SecurityMiddleware::validatePath($filePath);

        $fullPath = $this->buildFullPath($filePath);

        if (!@ftp_delete($this->connection, $fullPath)) {
            throw new \Exception("Failed to delete file");
        }

        return true;
    }

    /**
     * Move/Rename file or directory on FTP server
     */
    public function moveItem($sourcePath, $destinationPath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate paths
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
            // Maybe it's a directory, try to check if we can change to it
            $currentDir = @ftp_pwd($this->connection);
            if (!@ftp_chdir($this->connection, $fullSourcePath)) {
                throw new \Exception("Source path does not exist: '$fullSourcePath'");
            }
            // Go back to original directory
            @ftp_chdir($this->connection, $currentDir);
        }

        // Check if destination parent directory exists
        $destParent = dirname($fullDestPath);
        if ($destParent !== '/' && $destParent !== '.') {
            $currentDir = @ftp_pwd($this->connection);
            if (!@ftp_chdir($this->connection, $destParent)) {
                throw new \Exception("Destination directory does not exist: '$destParent'");
            }
            @ftp_chdir($this->connection, $currentDir);
        }

        // Try to rename/move
        if (!@ftp_rename($this->connection, $fullSourcePath, $fullDestPath)) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            throw new \Exception("Failed to move item: $errorMsg. Check FTP user permissions.");
        }

        return true;
    }

    /**
     * Delete directory from FTP server (recursively)
     */
    public function deleteDirectory($dirPath) {
        if (!$this->connection) {
            $this->connect();
        }

        // Validate path
        $dirPath = \App\Security\SecurityMiddleware::validatePath($dirPath);

        $fullPath = $this->buildFullPath($dirPath);

        // Get directory contents
        $files = @ftp_rawlist($this->connection, $fullPath);

        if ($files !== false) {
            foreach ($files as $item) {
                $parsed = $this->parseRawListItem($item);

                if (!$parsed || $parsed['name'] === '.' || $parsed['name'] === '..') {
                    continue;
                }

                $itemPath = rtrim($fullPath, '/') . '/' . $parsed['name'];

                if ($parsed['is_dir']) {
                    // Recursively delete subdirectory
                    $relativePath = ltrim(str_replace($basePath, '', $itemPath), '/');
                    $this->deleteDirectory($relativePath);
                } else {
                    // Delete file
                    @ftp_delete($this->connection, $itemPath);
                }
            }
        }

        // Delete the empty directory
        if (!@ftp_rmdir($this->connection, $fullPath)) {
            throw new \Exception("Failed to delete directory");
        }

        return true;
    }

    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
}
