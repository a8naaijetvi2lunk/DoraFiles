<?php

namespace App\Services;

/**
 * Service for user profile management
 *
 * Handles user profile operations, statistics, account updates,
 * and account deletion with security validations.
 */
class UserService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Get user profile data with statistics
     *
     * @param int $userId User ID
     * @return array|null User profile data or null if not found
     */
    public function getProfile(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, updated_at, last_login_at, last_login_ip
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // Get statistics
        $user['stats'] = $this->getUserStatistics($userId);

        return $user;
    }

    /**
     * Get user statistics (links, downloads, connections, activities)
     *
     * @param int $userId User ID
     * @return array Statistics array
     */
    public function getUserStatistics(int $userId): array
    {
        // Count active shared links
        $linksStmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_links,
                   COALESCE(SUM(download_count), 0) as total_downloads
            FROM shared_links
            WHERE user_id = ? AND revoked_at IS NULL
        ");
        $linksStmt->execute([$userId]);
        $linksData = $linksStmt->fetch();

        // Count FTP connections
        $ftpStmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_connections
            FROM ftp_connections
            WHERE user_id = ?
        ");
        $ftpStmt->execute([$userId]);
        $ftpData = $ftpStmt->fetch();

        // Count recent activities (last 30 days)
        $activityStmt = $this->pdo->prepare("
            SELECT COUNT(*) as recent_activities
            FROM activity_logs
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $activityStmt->execute([$userId]);
        $activityData = $activityStmt->fetch();

        return [
            'total_links' => (int) ($linksData['total_links'] ?? 0),
            'total_downloads' => (int) ($linksData['total_downloads'] ?? 0),
            'total_connections' => (int) ($ftpData['total_connections'] ?? 0),
            'recent_activities' => (int) ($activityData['recent_activities'] ?? 0)
        ];
    }

    /**
     * Update user email with validation
     *
     * @param int $userId User ID
     * @param string $newEmail New email address
     * @return bool|string True on success, error message on failure
     */
    public function updateEmail(int $userId, string $newEmail): bool|string
    {
        // Validate email format
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }

        // Sanitize email
        $newEmail = filter_var($newEmail, FILTER_SANITIZE_EMAIL);

        // Check if email already exists
        $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$newEmail, $userId]);

        if ($checkStmt->fetch()) {
            return 'Email already in use';
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newEmail, $userId]);

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'email_updated', 'user', $newEmail, 'Email address updated');

            return true;
        } catch (\PDOException $e) {
            error_log("Failed to update email: " . $e->getMessage());
            return 'Failed to update email';
        }
    }

    /**
     * Update user password with security checks
     *
     * @param int $userId User ID
     * @param string $currentPassword Current password for verification
     * @param string $newPassword New password
     * @return bool|string True on success, error message on failure
     */
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): bool|string
    {
        // Verify current password
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return 'Current password is incorrect';
        }

        // Validate new password strength
        if (strlen($newPassword) < 8) {
            return 'New password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $newPassword)) {
            return 'New password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $newPassword)) {
            return 'New password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            return 'New password must contain at least one number';
        }

        // Check if new password is same as current
        if (password_verify($newPassword, $user['password_hash'])) {
            return 'New password must be different from current password';
        }

        try {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $updateStmt = $this->pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newHash, $userId]);

            // Log activity
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'password_updated', 'user', null, 'Password changed');

            return true;
        } catch (\PDOException $e) {
            error_log("Failed to update password: " . $e->getMessage());
            return 'Failed to update password';
        }
    }

    /**
     * Update last login timestamp and IP
     *
     * @param int $userId User ID
     * @param string|null $ipAddress Client IP address
     * @return void
     */
    public function updateLastLogin(int $userId, ?string $ipAddress): void
    {
        // Validate and sanitize IP address
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null;

        $stmt = $this->pdo->prepare("
            UPDATE users
            SET last_login_at = NOW(), last_login_ip = ?
            WHERE id = ?
        ");
        $stmt->execute([$ipAddress, $userId]);
    }

    /**
     * Verify user password
     *
     * @param int $userId User ID
     * @param string $password Password to verify
     * @return bool True if password matches
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }

    /**
     * Delete user account and all associated data
     *
     * @param int $userId User ID
     * @param string $password Password confirmation required
     * @return bool|string True on success, error message on failure
     */
    public function deleteAccount(int $userId, string $password): bool|string
    {
        // Verify password
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return 'Password is incorrect';
        }

        try {
            $this->pdo->beginTransaction();

            // Cascade delete will handle related records (ftp_connections, activity_logs, shared_links)
            $deleteStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$userId]);

            $this->pdo->commit();

            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to delete account: " . $e->getMessage());
            return 'Failed to delete account';
        }
    }
}
