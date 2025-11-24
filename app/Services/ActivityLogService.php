<?php

namespace App\Services;

/**
 * Service for logging user activity
 *
 * Provides activity tracking, statistics, and audit logging
 * for user actions within the application.
 */
class ActivityLogService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Log a user activity
     *
     * @param int $userId User ID
     * @param string $action Action type (e.g., 'file_uploaded', 'link_created', 'login')
     * @param string|null $entityType Entity type (e.g., 'file', 'link', 'user')
     * @param string|null $entityName Name of the entity
     * @param string|null $details Additional details
     * @return bool True on success, false on failure
     */
    public function log(int $userId, string $action, ?string $entityType = null, ?string $entityName = null, ?string $details = null): bool
    {
        // Validate action
        if (!$this->isValidAction($action)) {
            error_log("Invalid activity action: {$action}");
            return false;
        }

        // Get client IP and user agent
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Sanitize user agent (limit length)
        if ($userAgent && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }

        // Sanitize entity name
        if ($entityName && strlen($entityName) > 255) {
            $entityName = substr($entityName, 0, 255);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_name, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityName,
                $details,
                $ipAddress,
                $userAgent
            ]);

            return true;

        } catch (\PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user activity logs with pagination
     *
     * @param int $userId User ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @param string|null $actionFilter Filter by action type
     * @return array Pagination data with 'logs', 'total', 'pages', 'current_page', 'per_page'
     */
    public function getUserActivity(int $userId, int $page = 1, int $perPage = 20, ?string $actionFilter = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage)); // Limit between 10 and 100
        $offset = ($page - 1) * $perPage;

        // Build query
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];

        if ($actionFilter && $this->isValidAction($actionFilter)) {
            $whereClause .= " AND action = ?";
            $params[] = $actionFilter;
        }

        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_logs {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Get logs
        $stmt = $this->pdo->prepare("
            SELECT id, action, entity_type, entity_name, details, ip_address, created_at
            FROM activity_logs
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        $logs = $stmt->fetchAll();

        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * Get recent activity for dashboard display
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of entries
     * @return array List of recent activities
     */
    public function getRecentActivity(int $userId, int $limit = 10): array
    {
        $limit = min(50, max(1, $limit)); // Limit between 1 and 50

        $stmt = $this->pdo->prepare("
            SELECT id, action, entity_type, entity_name, details, created_at
            FROM activity_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$userId, $limit]);

        return $stmt->fetchAll();
    }

    /**
     * Get activity statistics for a user
     *
     * @param int $userId User ID
     * @param int $days Number of days to look back
     * @return array Activity counts grouped by action type
     */
    public function getActivityStatistics(int $userId, int $days = 30): array
    {
        $days = min(365, max(1, $days));

        $stmt = $this->pdo->prepare("
            SELECT
                action,
                COUNT(*) as count
            FROM activity_logs
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ");

        $stmt->execute([$userId, $days]);

        return $stmt->fetchAll();
    }

    /**
     * Delete old activity logs (cleanup)
     *
     * @param int $daysToKeep Number of days to retain logs
     * @return int Number of deleted records
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $daysToKeep = max(30, $daysToKeep); // Minimum 30 days

        $stmt = $this->pdo->prepare("
            DELETE FROM activity_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");

        $stmt->execute([$daysToKeep]);

        return $stmt->rowCount();
    }

    /**
     * Get client IP address securely
     *
     * @return string|null Client IP address or null if not available
     */
    private function getClientIp(): ?string
    {
        $ipAddress = null;

        // Check for IP in various headers (be careful with proxies)
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        // Validate IP address
        if ($ipAddress && !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = null;
        }

        return $ipAddress;
    }

    /**
     * Check if action type is valid
     *
     * @param string $action Action to validate
     * @return bool True if valid action
     */
    private function isValidAction(string $action): bool
    {
        $validActions = [
            // Authentication
            'login',
            'logout',
            'login_failed',
            'login_2fa_required',
            'register',

            // User management
            'email_updated',
            'password_updated',
            'account_deleted',

            // FTP connections
            'ftp_connection_created',
            'ftp_connection_updated',
            'ftp_connection_deleted',
            'ftp_connection_switched',
            'ftp_connection_tested',

            // File operations
            'file_uploaded',
            'file_downloaded',
            'file_deleted',
            'file_renamed',
            'file_moved',
            'folder_created',
            'folder_deleted',

            // Link management
            'link_created',
            'link_deleted',
            'link_accessed',
            'link_download',

            // Browse operations
            'directory_browsed',
        ];

        return in_array($action, $validActions, true);
    }

    /**
     * Get human-readable action description
     *
     * @param string $action Action code
     * @return string Localized description
     */
    public static function getActionDescription(string $action): string
    {
        $descriptions = [
            // Authentication
            'login' => 'Connexion',
            'logout' => 'Deconnexion',
            'login_failed' => 'Echec de connexion',
            'login_2fa_required' => 'Verification 2FA requise',
            'register' => 'Inscription',

            // User management
            'email_updated' => 'Email modifie',
            'password_updated' => 'Mot de passe modifie',
            'account_deleted' => 'Compte supprime',

            // FTP connections
            'ftp_connection_created' => 'Connexion FTP creee',
            'ftp_connection_updated' => 'Connexion FTP modifiee',
            'ftp_connection_deleted' => 'Connexion FTP supprimee',
            'ftp_connection_switched' => 'Changement de connexion FTP',
            'ftp_connection_tested' => 'Test de connexion FTP',

            // File operations
            'file_uploaded' => 'Fichier envoye',
            'file_downloaded' => 'Fichier telecharge',
            'file_deleted' => 'Fichier supprime',
            'file_renamed' => 'Fichier renomme',
            'file_moved' => 'Fichier deplace',
            'folder_created' => 'Dossier cree',
            'folder_deleted' => 'Dossier supprime',

            // Link management
            'link_created' => 'Lien de partage cree',
            'link_deleted' => 'Lien de partage supprime',
            'link_accessed' => 'Lien de partage consulte',
            'link_download' => 'Telechargement via lien',

            // Browse operations
            'directory_browsed' => 'Navigation dans repertoire',
        ];

        return $descriptions[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Get action icon for UI display
     *
     * @param string $action Action code
     * @return string Icon character
     */
    public static function getActionIcon(string $action): string
    {
        $icons = [
            // Authentication
            'login' => '[+]',
            'logout' => '[-]',
            'login_failed' => '[x]',
            'login_2fa_required' => '[2]',
            'register' => '[*]',

            // User management
            'email_updated' => '[@]',
            'password_updated' => '[#]',
            'account_deleted' => '[D]',

            // FTP connections
            'ftp_connection_created' => '[C]',
            'ftp_connection_updated' => '[U]',
            'ftp_connection_deleted' => '[D]',
            'ftp_connection_switched' => '[S]',
            'ftp_connection_tested' => '[T]',

            // File operations
            'file_uploaded' => '[^]',
            'file_downloaded' => '[v]',
            'file_deleted' => '[x]',
            'file_renamed' => '[~]',
            'file_moved' => '[>]',
            'folder_created' => '[+]',
            'folder_deleted' => '[-]',

            // Link management
            'link_created' => '[L]',
            'link_deleted' => '[X]',
            'link_accessed' => '[O]',
            'link_download' => '[D]',

            // Browse operations
            'directory_browsed' => '[/]',
        ];

        return $icons[$action] ?? '[i]';
    }
}
