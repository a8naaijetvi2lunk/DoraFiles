<?php

namespace App\Services;

class AuthService {
    private $pdo;

    public function __construct() {
        $this->pdo = db();
    }

    /**
     * Authenticate user
     * Returns: true = full login, '2fa_required' = needs 2FA verification, false = failed
     */
    public function login($email, $password) {
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
            // Store user ID in session for 2FA verification
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['2fa_user_id'] = $user['id'];

            // Log 2FA required
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
     * Logout user
     */
    public function logout() {
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
     * Create new user
     */
    public function register($email, $password, $ftpHost, $ftpPort, $ftpUsername, $ftpPassword, $ftpBasePath = '/') {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Encrypt FTP credentials
        $ftpHostEnc = encrypt($ftpHost);
        $ftpPortEnc = encrypt($ftpPort);
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

            $userId = $this->pdo->lastInsertId();

            // Create default FTP connection
            $ftpService = new FTPConnectionService();
            $connectionId = $ftpService->createConnection(
                $userId,
                'Default Connection',
                $ftpHost,
                $ftpPort,
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
            return false;
        }
    }
}
