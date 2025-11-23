<?php

namespace App\Security;

class SecurityMiddleware {

    /**
     * Apply security headers to response
     */
    public static function applySecurityHeaders() {
        // Prevent XSS attacks
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

        // Prevent MIME sniffing
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HTTPS enforcement (if using HTTPS)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Prevent caching of sensitive pages
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Configure secure sessions
     */
    public static function configureSecureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure session configuration
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');

        // Regenerate session ID periodically
        session_start();

        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            // Regenerate session after 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    /**
     * Validate and sanitize path to prevent directory traversal
     */
    public static function validatePath($path) {
        if (empty($path)) {
            return '/';
        }

        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize path separators
        $path = str_replace('\\', '/', $path);

        // Remove dangerous patterns with loop to prevent bypass via double encoding
        $dangerous = ['../', '../', '..\\', '~/', './'];
        do {
            $before = $path;
            foreach ($dangerous as $pattern) {
                $path = str_replace($pattern, '', $path);
            }
        } while ($path !== $before); // Keep replacing until no more patterns found

        // Ensure path starts with /
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Remove consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove trailing slash unless it's root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        if (empty($email) || strlen($email) > 255) {
            return false;
        }

        // Filter and validate email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional checks for common issues
        if (strpos($email, '..') !== false) {
            return false;
        }

        return $email;
    }

    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        if (empty($password)) {
            return false;
        }

        // Minimum 8 characters
        if (strlen($password) < 8) {
            return false;
        }

        // Maximum 72 characters (bcrypt limitation)
        if (strlen($password) > 72) {
            return false;
        }

        return true;
    }

    /**
     * Validate token format (hexadecimal)
     */
    public static function validateToken($token) {
        if (empty($token)) {
            return false;
        }

        // Must be hexadecimal and reasonable length
        if (!preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize string input
     */
    public static function sanitizeString($input, $maxLength = 255) {
        if (empty($input)) {
            return '';
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Trim whitespace
        $input = trim($input);

        // Limit length
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }

        return $input;
    }

    /**
     * Validate FTP credentials format
     */
    public static function validateFTPHost($host) {
        if (empty($host) || strlen($host) > 255) {
            return false;
        }

        // Must be valid hostname or IP
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) &&
            !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return true;
    }

    /**
     * Validate FTP port
     */
    public static function validateFTPPort($port) {
        $port = filter_var($port, FILTER_VALIDATE_INT);

        if ($port === false || $port < 1 || $port > 65535) {
            return false;
        }

        return true;
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 300) {
        $rateLimitService = new \App\Services\RateLimitService();
        $identifier = \App\Services\RateLimitService::getClientIP() . ':' . $key;

        return $rateLimitService->check($identifier, $key, $maxAttempts, $timeWindow);
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $ip = \App\Services\RateLimitService::getClientIP();
        $timestamp = date('Y-m-d H:i:s');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] %s | IP: %s | UA: %s | Details: %s\n",
            $timestamp,
            $event,
            $ip,
            $userAgent,
            json_encode($details)
        );

        error_log($logEntry, 3, __DIR__ . '/../../storage/logs/security.log');
    }
}
