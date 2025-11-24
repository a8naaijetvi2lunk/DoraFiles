<?php

namespace App\Services;

/**
 * Service for managing FTP connections
 *
 * Handles CRUD operations for user FTP connections with encryption,
 * validation, and security checks.
 */
class FTPConnectionService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Get all FTP connections for a user
     *
     * @param int $userId User ID
     * @return array List of connections with decrypted display fields
     */
    public function getUserConnections(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, connection_name, ftp_host, ftp_port, ftp_username, ftp_base_path,
                   is_default, last_used_at, created_at, updated_at
            FROM ftp_connections
            WHERE user_id = ?
            ORDER BY is_default DESC, connection_name ASC
        ");
        $stmt->execute([$userId]);

        $connections = $stmt->fetchAll();

        // Decrypt FTP credentials for display (but not passwords)
        foreach ($connections as &$conn) {
            $conn['ftp_host_decrypted'] = decrypt($conn['ftp_host']);
            $conn['ftp_port_decrypted'] = decrypt($conn['ftp_port']);
            $conn['ftp_username_decrypted'] = decrypt($conn['ftp_username']);
            $conn['ftp_base_path_decrypted'] = $conn['ftp_base_path'] ? decrypt($conn['ftp_base_path']) : '/';
        }

        return $connections;
    }

    /**
     * Get a specific FTP connection with decrypted credentials
     *
     * @param int $connectionId Connection ID
     * @param int $userId User ID for authorization
     * @return array|null Connection data with decrypted fields or null if not found
     */
    public function getConnection(int $connectionId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, connection_name, ftp_host, ftp_port, ftp_username,
                   ftp_password, ftp_base_path, is_default, last_used_at
            FROM ftp_connections
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$connectionId, $userId]);

        $conn = $stmt->fetch();

        if (!$conn) {
            return null;
        }

        // Decrypt credentials
        $conn['ftp_host_decrypted'] = decrypt($conn['ftp_host']);
        $conn['ftp_port_decrypted'] = decrypt($conn['ftp_port']);
        $conn['ftp_username_decrypted'] = decrypt($conn['ftp_username']);
        $conn['ftp_password_decrypted'] = decrypt($conn['ftp_password']);
        $conn['ftp_base_path_decrypted'] = $conn['ftp_base_path'] ? decrypt($conn['ftp_base_path']) : '/';

        return $conn;
    }

    /**
     * Create a new FTP connection with validation and security checks
     *
     * @param int $userId User ID
     * @param string $connectionName Display name for the connection
     * @param string $ftpHost FTP server hostname
     * @param int $ftpPort FTP server port
     * @param string $ftpUsername FTP username
     * @param string $ftpPassword FTP password
     * @param string $ftpBasePath Base path on FTP server
     * @param bool $isDefault Whether this is the default connection
     * @return int|string Connection ID on success, error message on failure
     */
    public function createConnection(
        int $userId,
        string $connectionName,
        string $ftpHost,
        int $ftpPort,
        string $ftpUsername,
        string $ftpPassword,
        string $ftpBasePath = '/',
        bool $isDefault = false
    ): int|string {
        // Validate inputs
        $validation = $this->validateConnectionData($connectionName, $ftpHost, $ftpPort, $ftpUsername, $ftpPassword, $ftpBasePath);
        if ($validation !== true) {
            return $validation;
        }

        // Check connection limit per user (max 10 connections)
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ftp_connections WHERE user_id = ?");
        $countStmt->execute([$userId]);
        if ($countStmt->fetchColumn() >= 10) {
            return 'Maximum of 10 FTP connections allowed per user';
        }

        // Test FTP connection before saving
        $testResult = $this->testConnection($ftpHost, $ftpPort, $ftpUsername, $ftpPassword);
        if ($testResult !== true) {
            return $testResult;
        }

        try {
            $this->pdo->beginTransaction();

            // If setting as default, unset other defaults
            if ($isDefault) {
                $unsetStmt = $this->pdo->prepare("UPDATE ftp_connections SET is_default = 0 WHERE user_id = ?");
                $unsetStmt->execute([$userId]);
            }

            // Encrypt credentials
            $ftpHostEnc = encrypt($ftpHost);
            $ftpPortEnc = encrypt((string)$ftpPort);
            $ftpUsernameEnc = encrypt($ftpUsername);
            $ftpPasswordEnc = encrypt($ftpPassword);
            $ftpBasePathEnc = encrypt($ftpBasePath);

            $stmt = $this->pdo->prepare("
                INSERT INTO ftp_connections (user_id, connection_name, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $connectionName,
                $ftpHostEnc,
                $ftpPortEnc,
                $ftpUsernameEnc,
                $ftpPasswordEnc,
                $ftpBasePathEnc,
                $isDefault ? 1 : 0
            ]);

            $connectionId = (int) $this->pdo->lastInsertId();

            // Update user's active connection if this is default
            if ($isDefault) {
                $updateUserStmt = $this->pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?");
                $updateUserStmt->execute([$connectionId, $userId]);
            }

            $this->pdo->commit();

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'ftp_connection_created', 'ftp_connection', $connectionName, "FTP connection '{$connectionName}' created");

            return $connectionId;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to create FTP connection: " . $e->getMessage());
            return 'Failed to create FTP connection';
        }
    }

    /**
     * Update an existing FTP connection
     *
     * @param int $connectionId Connection ID
     * @param int $userId User ID for authorization
     * @param string $connectionName Display name for the connection
     * @param string $ftpHost FTP server hostname
     * @param int $ftpPort FTP server port
     * @param string $ftpUsername FTP username
     * @param string|null $ftpPassword FTP password (null to keep existing)
     * @param string $ftpBasePath Base path on FTP server
     * @param bool $isDefault Whether this is the default connection
     * @return bool|string True on success, error message on failure
     */
    public function updateConnection(
        int $connectionId,
        int $userId,
        string $connectionName,
        string $ftpHost,
        int $ftpPort,
        string $ftpUsername,
        ?string $ftpPassword,
        string $ftpBasePath = '/',
        bool $isDefault = false
    ): bool|string {
        // Verify ownership
        $existing = $this->getConnection($connectionId, $userId);
        if (!$existing) {
            return 'Connection not found or access denied';
        }

        // Validate inputs
        $validation = $this->validateConnectionData($connectionName, $ftpHost, $ftpPort, $ftpUsername, $ftpPassword ?: 'dummy', $ftpBasePath);
        if ($validation !== true) {
            return $validation;
        }

        // If password is provided, test connection
        if ($ftpPassword) {
            $testResult = $this->testConnection($ftpHost, $ftpPort, $ftpUsername, $ftpPassword);
            if ($testResult !== true) {
                return $testResult;
            }
        }

        try {
            $this->pdo->beginTransaction();

            // If setting as default, unset other defaults
            if ($isDefault) {
                $unsetStmt = $this->pdo->prepare("UPDATE ftp_connections SET is_default = 0 WHERE user_id = ?");
                $unsetStmt->execute([$userId]);
            }

            // Encrypt credentials
            $ftpHostEnc = encrypt($ftpHost);
            $ftpPortEnc = encrypt((string)$ftpPort);
            $ftpUsernameEnc = encrypt($ftpUsername);
            $ftpBasePathEnc = encrypt($ftpBasePath);

            if ($ftpPassword) {
                $ftpPasswordEnc = encrypt($ftpPassword);
                $stmt = $this->pdo->prepare("
                    UPDATE ftp_connections
                    SET connection_name = ?, ftp_host = ?, ftp_port = ?, ftp_username = ?,
                        ftp_password = ?, ftp_base_path = ?, is_default = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $connectionName, $ftpHostEnc, $ftpPortEnc, $ftpUsernameEnc,
                    $ftpPasswordEnc, $ftpBasePathEnc, $isDefault ? 1 : 0,
                    $connectionId, $userId
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE ftp_connections
                    SET connection_name = ?, ftp_host = ?, ftp_port = ?, ftp_username = ?,
                        ftp_base_path = ?, is_default = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $connectionName, $ftpHostEnc, $ftpPortEnc, $ftpUsernameEnc,
                    $ftpBasePathEnc, $isDefault ? 1 : 0,
                    $connectionId, $userId
                ]);
            }

            // Update user's active connection if this is default
            if ($isDefault) {
                $updateUserStmt = $this->pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?");
                $updateUserStmt->execute([$connectionId, $userId]);
            }

            $this->pdo->commit();

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'ftp_connection_updated', 'ftp_connection', $connectionName, "FTP connection '{$connectionName}' updated");

            return true;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to update FTP connection: " . $e->getMessage());
            return 'Failed to update FTP connection';
        }
    }

    /**
     * Delete an FTP connection
     *
     * @param int $connectionId Connection ID
     * @param int $userId User ID for authorization
     * @return bool|string True on success, error message on failure
     */
    public function deleteConnection(int $connectionId, int $userId): bool|string
    {
        // Verify ownership
        $existing = $this->getConnection($connectionId, $userId);
        if (!$existing) {
            return 'Connection not found or access denied';
        }

        // Prevent deletion if it's the only connection
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ftp_connections WHERE user_id = ?");
        $countStmt->execute([$userId]);
        if ($countStmt->fetchColumn() <= 1) {
            return 'Cannot delete the last FTP connection';
        }

        try {
            $connectionName = $existing['connection_name'];

            $stmt = $this->pdo->prepare("DELETE FROM ftp_connections WHERE id = ? AND user_id = ?");
            $stmt->execute([$connectionId, $userId]);

            // If this was the active connection, set another as active
            $userStmt = $this->pdo->prepare("SELECT active_ftp_connection_id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            if ($user && $user['active_ftp_connection_id'] == $connectionId) {
                // Get first remaining connection
                $firstConn = $this->pdo->prepare("SELECT id FROM ftp_connections WHERE user_id = ? LIMIT 1");
                $firstConn->execute([$userId]);
                $newActive = $firstConn->fetch();

                if ($newActive) {
                    $updateUser = $this->pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?");
                    $updateUser->execute([$newActive['id'], $userId]);
                }
            }

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'ftp_connection_deleted', 'ftp_connection', $connectionName, "FTP connection '{$connectionName}' deleted");

            return true;

        } catch (\PDOException $e) {
            error_log("Failed to delete FTP connection: " . $e->getMessage());
            return 'Failed to delete FTP connection';
        }
    }

    /**
     * Switch active FTP connection
     *
     * @param int $connectionId Connection ID to switch to
     * @param int $userId User ID for authorization
     * @return bool|string True on success, error message on failure
     */
    public function switchConnection(int $connectionId, int $userId): bool|string
    {
        // Verify ownership
        $conn = $this->getConnection($connectionId, $userId);
        if (!$conn) {
            return 'Connection not found or access denied';
        }

        try {
            // Update user's active connection
            $stmt = $this->pdo->prepare("UPDATE users SET active_ftp_connection_id = ? WHERE id = ?");
            $stmt->execute([$connectionId, $userId]);

            // Update last_used_at
            $updateConn = $this->pdo->prepare("UPDATE ftp_connections SET last_used_at = NOW() WHERE id = ?");
            $updateConn->execute([$connectionId]);

            // Update session
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['active_ftp_connection_id'] = $connectionId;
                $_SESSION['user']['ftp_host_decrypted'] = $conn['ftp_host_decrypted'];
                $_SESSION['user']['ftp_port_decrypted'] = $conn['ftp_port_decrypted'];
                $_SESSION['user']['ftp_username_decrypted'] = $conn['ftp_username_decrypted'];
                $_SESSION['user']['ftp_password_decrypted'] = $conn['ftp_password_decrypted'];
                $_SESSION['user']['ftp_base_path_decrypted'] = $conn['ftp_base_path_decrypted'];
            }

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'ftp_connection_switched', 'ftp_connection', $conn['connection_name'], "Switched to FTP connection '{$conn['connection_name']}'");

            return true;

        } catch (\PDOException $e) {
            error_log("Failed to switch FTP connection: " . $e->getMessage());
            return 'Failed to switch FTP connection';
        }
    }

    /**
     * Test FTP connection credentials
     *
     * @param string $ftpHost FTP server hostname
     * @param int $ftpPort FTP server port
     * @param string $ftpUsername FTP username
     * @param string $ftpPassword FTP password
     * @return bool|string True on success, error message on failure
     */
    private function testConnection(string $ftpHost, int $ftpPort, string $ftpUsername, string $ftpPassword): bool|string
    {
        $timeout = (int)env('FTP_TIMEOUT', 10);

        $conn = @ftp_connect($ftpHost, $ftpPort, $timeout);
        if (!$conn) {
            return "Cannot connect to FTP server {$ftpHost}:{$ftpPort}";
        }

        $login = @ftp_login($conn, $ftpUsername, $ftpPassword);
        if (!$login) {
            ftp_close($conn);
            return 'FTP authentication failed - invalid credentials';
        }

        ftp_close($conn);
        return true;
    }

    /**
     * Validate connection data
     *
     * @param string $connectionName Connection display name
     * @param string $ftpHost FTP server hostname
     * @param int $ftpPort FTP server port
     * @param string $ftpUsername FTP username
     * @param string $ftpPassword FTP password
     * @param string $ftpBasePath Base path on FTP server
     * @return bool|string True if valid, error message otherwise
     */
    private function validateConnectionData(
        string $connectionName,
        string $ftpHost,
        int $ftpPort,
        string $ftpUsername,
        string $ftpPassword,
        string $ftpBasePath
    ): bool|string {
        // Validate connection name
        if (empty($connectionName) || strlen($connectionName) > 100) {
            return 'Connection name must be between 1 and 100 characters';
        }

        // Sanitize and validate FTP host
        $ftpHost = trim($ftpHost);
        if (empty($ftpHost) || strlen($ftpHost) > 255) {
            return 'Invalid FTP host';
        }

        // Validate port
        if ($ftpPort < 1 || $ftpPort > 65535) {
            return 'FTP port must be between 1 and 65535';
        }

        // Validate username
        if (empty($ftpUsername) || strlen($ftpUsername) > 255) {
            return 'Invalid FTP username';
        }

        // Validate password
        if (empty($ftpPassword) || strlen($ftpPassword) > 255) {
            return 'Invalid FTP password';
        }

        // Validate base path
        $ftpBasePath = trim($ftpBasePath);
        if (empty($ftpBasePath)) {
            $ftpBasePath = '/';
        }

        // Prevent path traversal
        if (strpos($ftpBasePath, '..') !== false) {
            return 'Invalid base path - path traversal not allowed';
        }

        return true;
    }
}
