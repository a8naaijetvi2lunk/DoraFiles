<?php

namespace App\Services;

/**
 * Service for rate limiting requests
 *
 * Provides IP-based rate limiting to prevent brute force attacks
 * and abuse of sensitive endpoints.
 */
class RateLimitService
{
    private \PDO $pdo;
    private int $limit;
    private int $windowMinutes;

    /**
     * Create a new rate limit service
     *
     * @param int|null $limit Maximum attempts allowed (defaults to env DOWNLOAD_RATE_LIMIT or 50)
     * @param int $windowMinutes Time window in minutes
     */
    public function __construct(?int $limit = null, int $windowMinutes = 1)
    {
        $this->pdo = db();
        $this->limit = $limit ?? (int) env('DOWNLOAD_RATE_LIMIT', 50);
        $this->windowMinutes = $windowMinutes;
    }

    /**
     * Check if request is allowed (not rate limited)
     *
     * @param string $ipAddress Client IP address
     * @param string $action Action being rate limited (e.g., 'download', 'login')
     * @return bool True if allowed, false if rate limited
     */
    public function check(string $ipAddress, string $action = 'download'): bool
    {
        // Clean old entries
        $this->cleanOldEntries();

        // Get current attempts
        $stmt = $this->pdo->prepare("
            SELECT attempts FROM rate_limits
            WHERE ip_address = ?
            AND action = ?
            AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");

        $stmt->execute([$ipAddress, $action, $this->windowMinutes]);
        $result = $stmt->fetch();

        if ($result && $result['attempts'] >= $this->limit) {
            return false; // Rate limited
        }

        // Increment or create entry
        $this->increment($ipAddress, $action);

        return true; // Allowed
    }

    /**
     * Increment rate limit counter for an IP/action pair
     *
     * @param string $ipAddress Client IP address
     * @param string $action Action being tracked
     * @return void
     */
    private function increment(string $ipAddress, string $action): void
    {
        // Try to update existing entry
        $stmt = $this->pdo->prepare("
            UPDATE rate_limits
            SET attempts = attempts + 1
            WHERE ip_address = ?
            AND action = ?
            AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");

        $stmt->execute([$ipAddress, $action, $this->windowMinutes]);

        // If no rows updated, create new entry
        if ($stmt->rowCount() === 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (ip_address, action, attempts, window_start)
                VALUES (?, ?, 1, NOW())
            ");

            $stmt->execute([$ipAddress, $action]);
        }
    }

    /**
     * Clean expired rate limit entries
     *
     * @return void
     */
    private function cleanOldEntries(): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits
            WHERE window_start < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");

        $stmt->execute([$this->windowMinutes + 5]); // Keep extra 5 minutes buffer
    }

    /**
     * Get client IP address with spoofing protection
     *
     * Only trusts proxy headers if the immediate client IP
     * is in the configured TRUSTED_PROXIES list.
     *
     * @return string Client IP address (defaults to '0.0.0.0' if invalid)
     */
    public static function getClientIP(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust proxy headers if behind verified reverse proxy
        $trustedProxies = array_filter(
            explode(',', env('TRUSTED_PROXIES', '')),
            fn($proxy) => !empty(trim($proxy))
        );

        // Only check forwarded headers if current IP is a trusted proxy
        if (!empty($trustedProxies) && in_array($ip, array_map('trim', $trustedProxies))) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                // Get the first (leftmost) IP which is the original client IP
                $ip = $ips[0];
            }
        }

        // Validate IP format to prevent injection
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            error_log("Invalid IP format detected: " . var_export($ip, true));
            return '0.0.0.0';
        }

        return $ip;
    }
}
