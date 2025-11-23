<?php

namespace App\Services;

class ActivityLogService {
    private $pdo;

    public function __construct() {
        $this->pdo = db();
    }

    /**
     * Log an activity
     *
     * @param int $userId
     * @param string $action Action type (e.g., 'file_uploaded', 'link_created', 'login')
     * @param string|null $entityType Entity type (e.g., 'file', 'link', 'user')
     * @param string|null $entityName Name of the entity
     * @param string|null $details Additional details
     * @return bool
     */
    public function log($userId, $action, $entityType = null, $entityName = null, $details = null) {
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
     * @param int $userId
     * @param int $page
     * @param int $perPage
     * @param string|null $actionFilter Filter by action type
     * @return array ['logs' => array, 'total' => int, 'pages' => int]
     */
    public function getUserActivity($userId, $page = 1, $perPage = 20, $actionFilter = null) {
        $page = max(1, (int)$page);
        $perPage = min(100, max(10, (int)$perPage)); // Limit between 10 and 100
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
        $total = $countStmt->fetchColumn();

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
            'pages' => ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * Get recent activity for dashboard
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getRecentActivity($userId, $limit = 10) {
        $limit = min(50, max(1, (int)$limit)); // Limit between 1 and 50

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
     * @param int $userId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getActivityStatistics($userId, $days = 30) {
        $days = min(365, max(1, (int)$days));

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
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function cleanupOldLogs($daysToKeep = 90) {
        $daysToKeep = max(30, (int)$daysToKeep); // Minimum 30 days

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
     * @return string|null
     */
    private function getClientIp() {
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
     * @param string $action
     * @return bool
     */
    private function isValidAction($action) {
        $validActions = [
            // Authentication
            'login',
            'logout',
            'login_failed',
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
     * @param string $action
     * @return string
     */
    public static function getActionDescription($action) {
        $descriptions = [
            // Authentication
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'login_failed' => 'Échec de connexion',
            'register' => 'Inscription',

            // User management
            'email_updated' => 'Email modifié',
            'password_updated' => 'Mot de passe modifié',
            'account_deleted' => 'Compte supprimé',

            // FTP connections
            'ftp_connection_created' => 'Connexion FTP créée',
            'ftp_connection_updated' => 'Connexion FTP modifiée',
            'ftp_connection_deleted' => 'Connexion FTP supprimée',
            'ftp_connection_switched' => 'Changement de connexion FTP',
            'ftp_connection_tested' => 'Test de connexion FTP',

            // File operations
            'file_uploaded' => 'Fichier envoyé',
            'file_downloaded' => 'Fichier téléchargé',
            'file_deleted' => 'Fichier supprimé',
            'file_renamed' => 'Fichier renommé',
            'file_moved' => 'Fichier déplacé',
            'folder_created' => 'Dossier créé',
            'folder_deleted' => 'Dossier supprimé',

            // Link management
            'link_created' => 'Lien de partage créé',
            'link_deleted' => 'Lien de partage supprimé',
            'link_accessed' => 'Lien de partage consulté',
            'link_download' => 'Téléchargement via lien',

            // Browse operations
            'directory_browsed' => 'Navigation dans répertoire',
        ];

        return $descriptions[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Get action icon emoji for UI
     *
     * @param string $action
     * @return string
     */
    public static function getActionIcon($action) {
        $icons = [
            // Authentication
            'login' => '🔓',
            'logout' => '🔒',
            'login_failed' => '❌',
            'register' => '✨',

            // User management
            'email_updated' => '📧',
            'password_updated' => '🔑',
            'account_deleted' => '🗑️',

            // FTP connections
            'ftp_connection_created' => '🔌',
            'ftp_connection_updated' => '⚙️',
            'ftp_connection_deleted' => '🔌',
            'ftp_connection_switched' => '🔄',
            'ftp_connection_tested' => '✅',

            // File operations
            'file_uploaded' => '⬆️',
            'file_downloaded' => '⬇️',
            'file_deleted' => '🗑️',
            'file_renamed' => '✏️',
            'file_moved' => '📤',
            'folder_created' => '📁',
            'folder_deleted' => '🗑️',

            // Link management
            'link_created' => '🔗',
            'link_deleted' => '⛓️',
            'link_accessed' => '👁️',
            'link_download' => '📥',

            // Browse operations
            'directory_browsed' => '📂',
        ];

        return $icons[$action] ?? 'ℹ️';
    }
}
