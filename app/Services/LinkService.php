<?php

namespace App\Services;

/**
 * Service for managing shared download links
 *
 * Handles creation, verification, and management of file sharing links
 * with support for password protection and expiration.
 */
class LinkService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Create a new share link
     *
     * @param int $userId User ID
     * @param int|null $ftpConnectionId FTP connection ID
     * @param string $filePath File path on FTP server
     * @param string $fileName Display file name
     * @param int $fileSize File size in bytes
     * @param string|null $expiresIn Expiration time (e.g., '+7 days')
     * @param string|null $password Optional password protection
     * @return array Link data with id, token, url, has_password
     */
    public function createLink(
        int $userId,
        ?int $ftpConnectionId,
        string $filePath,
        string $fileName,
        int $fileSize,
        ?string $expiresIn = null,
        ?string $password = null
    ): array {
        $token = generateToken(32);

        $expiresAt = null;
        if ($expiresIn) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($expiresIn));
        }

        $passwordHash = null;
        if ($password) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO shared_links (user_id, ftp_connection_id, token, file_path, file_name, file_size, password_hash, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $ftpConnectionId,
            $token,
            $filePath,
            $fileName,
            $fileSize,
            $passwordHash,
            $expiresAt
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'token' => $token,
            'url' => env('APP_URL') . '/dl/' . $token,
            'has_password' => !empty($password)
        ];
    }

    /**
     * Get link by token (includes FTP credentials for download)
     *
     * @param string $token Link token
     * @return array|false Link data or false if not found/expired/revoked
     */
    public function getLinkByToken(string $token): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT
                sl.id,
                sl.token,
                sl.file_path,
                sl.file_name,
                sl.file_size,
                sl.password_hash,
                sl.expires_at,
                sl.download_count,
                sl.created_at,
                sl.last_downloaded_at,
                COALESCE(fc.ftp_host, u.ftp_host) as ftp_host,
                COALESCE(fc.ftp_port, u.ftp_port) as ftp_port,
                COALESCE(fc.ftp_username, u.ftp_username) as ftp_username,
                COALESCE(fc.ftp_password, u.ftp_password) as ftp_password,
                COALESCE(fc.ftp_base_path, u.ftp_base_path) as ftp_base_path
            FROM shared_links sl
            JOIN users u ON sl.user_id = u.id
            LEFT JOIN ftp_connections fc ON sl.ftp_connection_id = fc.id
            WHERE sl.token = ?
            AND sl.revoked_at IS NULL
            AND (sl.expires_at IS NULL OR sl.expires_at > NOW())
        ");

        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    /**
     * Verify password for a protected link
     *
     * @param array $link Link data
     * @param string $password Password to verify
     * @return bool True if password matches or no password required
     */
    public function verifyPassword(array $link, string $password): bool
    {
        if (empty($link['password_hash'])) {
            return true; // No password required
        }

        return password_verify($password, $link['password_hash']);
    }

    /**
     * Get all links for a user
     *
     * @param int $userId User ID
     * @return array List of links
     */
    public function getUserLinks(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sl.*,
                   CASE WHEN sl.password_hash IS NOT NULL THEN 1 ELSE 0 END as has_password,
                   fc.connection_name as ftp_connection_name
            FROM shared_links sl
            LEFT JOIN ftp_connections fc ON sl.ftp_connection_id = fc.id
            WHERE sl.user_id = ?
            ORDER BY sl.created_at DESC
        ");

        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Increment download count for a link
     *
     * @param int $linkId Link ID
     * @return void
     */
    public function incrementDownload(int $linkId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE shared_links
            SET download_count = download_count + 1,
                last_downloaded_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$linkId]);
    }

    /**
     * Revoke a link (soft delete)
     *
     * @param int $linkId Link ID
     * @param int $userId User ID (for authorization)
     * @return void
     */
    public function revokeLink(int $linkId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE shared_links
            SET revoked_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$linkId, $userId]);
    }

    /**
     * Permanently delete a link
     *
     * @param int $linkId Link ID
     * @param int $userId User ID (for authorization)
     * @return void
     */
    public function deleteLink(int $linkId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM shared_links
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$linkId, $userId]);
    }
}
