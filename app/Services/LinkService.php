<?php

namespace App\Services;

class LinkService {
    private $pdo;

    public function __construct() {
        $this->pdo = db();
    }

    /**
     * Create a new share link
     */
    public function createLink($userId, $ftpConnectionId, $filePath, $fileName, $fileSize, $expiresIn = null, $password = null) {
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
            'id' => $this->pdo->lastInsertId(),
            'token' => $token,
            'url' => env('APP_URL') . '/dl/' . $token,
            'has_password' => !empty($password)
        ];
    }

    /**
     * Get link by token
     */
    public function getLinkByToken($token) {
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
     * Verify password for a link
     */
    public function verifyPassword($link, $password) {
        if (empty($link['password_hash'])) {
            return true; // No password required
        }

        return password_verify($password, $link['password_hash']);
    }

    /**
     * Get all links for user
     */
    public function getUserLinks($userId) {
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
     * Increment download count
     */
    public function incrementDownload($linkId) {
        $stmt = $this->pdo->prepare("
            UPDATE shared_links
            SET download_count = download_count + 1,
                last_downloaded_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$linkId]);
    }

    /**
     * Revoke link
     */
    public function revokeLink($linkId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE shared_links
            SET revoked_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$linkId, $userId]);
    }

    /**
     * Delete link
     */
    public function deleteLink($linkId, $userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM shared_links
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$linkId, $userId]);
    }
}
