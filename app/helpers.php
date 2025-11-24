<?php

/**
 * Load environment variables from .env file with error handling
 */
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log("Failed to read .env file: $path");
        return;
    }

    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Check if line contains '='
        if (strpos($line, '=') === false) {
            error_log("Malformed .env line " . ($lineNumber + 1) . ": $line");
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            error_log("Invalid .env line " . ($lineNumber + 1) . ": $line");
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        // Validate variable name (alphanumeric and underscore only)
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) {
            error_log("Invalid environment variable name on line " . ($lineNumber + 1) . ": $name");
            continue;
        }

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

/**
 * Get environment variable
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    return $value;
}

/**
 * Get validated encryption key
 * @throws RuntimeException if key is not configured
 */
function getEncryptionKey(): string {
    $key = env('APP_ENCRYPTION_KEY');
    if (empty($key)) {
        throw new \RuntimeException('APP_ENCRYPTION_KEY not configured');
    }
    return base64_decode(str_replace('base64:', '', $key));
}

/**
 * Encrypt data using AES-256-CBC with HMAC authentication
 * SECURITY FIX: Uses random_bytes() instead of openssl_random_pseudo_bytes()
 * SECURITY FIX: Added HMAC for integrity verification
 */
function encrypt($data) {
    $key = getEncryptionKey();

    // SECURITY FIX: Use cryptographically secure random_bytes()
    $iv = random_bytes(16);

    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    // SECURITY FIX: Verify encryption succeeded
    if ($encrypted === false) {
        throw new \RuntimeException('Encryption failed');
    }

    // SECURITY FIX: Add HMAC for integrity (encrypt-then-MAC)
    $payload = $iv . $encrypted;
    $hmac = hash_hmac('sha256', $payload, $key, true);

    return base64_encode($hmac . $payload);
}

/**
 * Decrypt data using AES-256-CBC with HMAC verification
 * SECURITY FIX: Verifies HMAC before decryption (prevents padding oracle attacks)
 */
function decrypt($data) {
    $key = getEncryptionKey();
    $data = base64_decode($data);

    // Check minimum length (32 bytes HMAC + 16 bytes IV + at least 1 byte encrypted)
    if (strlen($data) < 49) {
        // Legacy data without HMAC - try old format for backward compatibility
        return decryptLegacy($data, $key);
    }

    // Extract HMAC, IV, and encrypted data
    $hmac = substr($data, 0, 32);
    $payload = substr($data, 32);

    // SECURITY FIX: Verify HMAC before decryption (timing-safe comparison)
    $expectedHmac = hash_hmac('sha256', $payload, $key, true);
    if (!hash_equals($hmac, $expectedHmac)) {
        // Try legacy format for backward compatibility
        return decryptLegacy(base64_decode(base64_encode($data)), $key);
    }

    $iv = substr($payload, 0, 16);
    $encrypted = substr($payload, 16);

    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        throw new \RuntimeException('Decryption failed');
    }

    return $decrypted;
}

/**
 * Legacy decrypt function for backward compatibility with old encrypted data
 */
function decryptLegacy($data, $key) {
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);

    // Old format stored base64 of encrypted data, new format stores raw
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

    if ($decrypted === false) {
        // Try with raw data
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    return $decrypted;
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get current authenticated user
 */
function auth() {
    if (session_status() === PHP_SESSION_NONE) {
        \App\Security\SecurityMiddleware::configureSecureSession();
    }

    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return auth() !== null;
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Render view
 */
function view($view, $data = []) {
    extract($data);
    $viewPath = __DIR__ . "/../views/{$view}.php";

    if (!file_exists($viewPath)) {
        die("View not found: {$view}");
    }

    require $viewPath;
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Escape HTML
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get CSRF token
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        \App\Security\SecurityMiddleware::configureSecureSession();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function csrf_verify($token) {
    return hash_equals(csrf_token(), $token);
}

/**
 * Get database connection
 */
function db() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST'),
            env('DB_PORT', 3306),
            env('DB_DATABASE')
        );

        $pdo = new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

/**
 * Get the current active FTP connection and prepare user data with credentials
 * This is used across all FTP-related pages to ensure consistent connection handling
 *
 * @return array User data with FTP credentials
 * @throws Exception If no FTP connection is configured
 */
function getFTPUserData() {
    $user = auth();

    // Get the active FTP connection (respects session-based browsing selection)
    $ftpConnectionService = new \App\Services\FTPConnectionService();
    $browseConnectionId = $_SESSION['browse_ftp_connection_id'] ?? $user['active_ftp_connection_id'] ?? null;

    // Get the full connection with decrypted credentials
    $currentConnection = null;
    if ($browseConnectionId) {
        $currentConnection = $ftpConnectionService->getConnection($browseConnectionId, $user['id']);
    }

    // Fallback to first connection if none found
    if (!$currentConnection) {
        $allConnections = $ftpConnectionService->getUserConnections($user['id']);
        if (!empty($allConnections)) {
            $currentConnection = $ftpConnectionService->getConnection($allConnections[0]['id'], $user['id']);
        }
    }

    if (!$currentConnection) {
        throw new Exception('Aucune connexion FTP configurée.');
    }

    // Prepare user data with selected connection credentials
    $browseUser = $user;
    $browseUser['ftp_host_decrypted'] = $currentConnection['ftp_host_decrypted'];
    $browseUser['ftp_port_decrypted'] = $currentConnection['ftp_port_decrypted'];
    $browseUser['ftp_username_decrypted'] = $currentConnection['ftp_username_decrypted'];
    $browseUser['ftp_password_decrypted'] = $currentConnection['ftp_password_decrypted'];
    $browseUser['ftp_base_path_decrypted'] = $currentConnection['ftp_base_path_decrypted'];

    return $browseUser;
}
