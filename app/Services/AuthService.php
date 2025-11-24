<?php

namespace App\Services;

/**
 * Service for user authentication
 *
 * Handles user login, logout, registration, and session management
 * with support for 2FA and activity logging.
 */
class AuthService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Authenticate user with email and password
     *
     * @param string $email User email
     * @param string $password User password
     * @return bool|string True on success, '2fa_required' if 2FA needed, false on failure
     */
    public function login(string $email, string $password): bool|string
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Log failed login attempt
            if ($user) {
                $activityLog = new ActivityLogService();
                $activityLog->log($user['id'], 'login_failed', 'user', $email, 'Failed login attempt');
            }
            return false;
        }

        // Check if 2FA is enabled
        if ($user['two_factor_enabled'] == 1) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['2fa_user_id'] = $user['id'];

            $activityLog = new ActivityLogService();
            $activityLog->log($user['id'], 'login_2fa_required', 'user', $email, '2FA verification required');

            return '2fa_required';
        }

        // Load active FTP connection or fallback to legacy fields
        if ($user['active_ftp_connection_id']) {
            $ftpService = new FTPConnectionService();
            $activeConn = $ftpService->getConnection($user['active_ftp_connection_id'], $user['id']);

            if ($activeConn) {
                $user['ftp_host_decrypted'] = $activeConn['ftp_host_decrypted'];
                $user['ftp_port_decrypted'] = $activeConn['ftp_port_decrypted'];
                $user['ftp_username_decrypted'] = $activeConn['ftp_username_decrypted'];
                $user['ftp_password_decrypted'] = $activeConn['ftp_password_decrypted'];
                $user['ftp_base_path_decrypted'] = $activeConn['ftp_base_path_decrypted'];
            }
        } else {
            // Fallback to legacy direct fields
            $user['ftp_host_decrypted'] = decrypt($user['ftp_host']);
            $user['ftp_port_decrypted'] = decrypt($user['ftp_port']);
            $user['ftp_username_decrypted'] = decrypt($user['ftp_username']);
            $user['ftp_password_decrypted'] = decrypt($user['ftp_password']);
            $user['ftp_base_path_decrypted'] = $user['ftp_base_path'] ? decrypt($user['ftp_base_path']) : '/';
        }

        // Store in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user'] = $user;

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Update last login info
        $userService = new UserService();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userService->updateLastLogin($user['id'], $ipAddress);

        // Log successful login
        $activityLog = new ActivityLogService();
        $activityLog->log($user['id'], 'login', 'user', $email, 'User logged in successfully');

        return true;
    }

    /**
     * Logout user and destroy session
     *
     * @return void
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log logout before destroying session
        if (isset($_SESSION['user'])) {
            $activityLog = new ActivityLogService();
            $activityLog->log($_SESSION['user']['id'], 'logout', 'user', $_SESSION['user']['email'], 'User logged out');
        }

        $_SESSION = [];
        session_destroy();
    }

    /**
     * Register a new user with FTP credentials
     *
     * @param string $email User email
     * @param string $password User password
     * @param string $ftpHost FTP server hostname
     * @param int|string $ftpPort FTP server port
     * @param string $ftpUsername FTP username
     * @param string $ftpPassword FTP password
     * @param string $ftpBasePath FTP base path
     * @return bool True on success, false on failure
     */
    public function register(
        string $email,
        string $password,
        string $ftpHost,
        int|string $ftpPort,
        string $ftpUsername,
        string $ftpPassword,
        string $ftpBasePath = '/'
    ): bool {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Encrypt FTP credentials
        $ftpHostEnc = encrypt($ftpHost);
        $ftpPortEnc = encrypt((string) $ftpPort);
        $ftpUsernameEnc = encrypt($ftpUsername);
        $ftpPasswordEnc = encrypt($ftpPassword);
        $ftpBasePathEnc = encrypt($ftpBasePath);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $email,
                $passwordHash,
                $ftpHostEnc,
                $ftpPortEnc,
                $ftpUsernameEnc,
                $ftpPasswordEnc,
                $ftpBasePathEnc
            ]);

            $userId = (int) $this->pdo->lastInsertId();

            // Create default FTP connection
            $ftpService = new FTPConnectionService();
            $ftpService->createConnection(
                $userId,
                'Default Connection',
                $ftpHost,
                (int) $ftpPort,
                $ftpUsername,
                $ftpPassword,
                $ftpBasePath,
                true
            );

            // Log registration
            $activityLog = new ActivityLogService();
            $activityLog->log($userId, 'register', 'user', $email, 'New user registered');

            return true;
        } catch (\PDOException $e) {
            error_log("Registration failed for {$email}: " . $e->getMessage());
            return false;
        }
    }
}
