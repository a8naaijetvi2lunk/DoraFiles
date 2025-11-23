<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

use App\Services\TwoFactorService;
use App\Services\ActivityLogService;

// Check if user is in 2FA verification state
if (!isset($_SESSION['2fa_user_id'])) {
    redirect('/login.php');
}

$error = null;
$userId = $_SESSION['2fa_user_id'];
$twoFactorService = new TwoFactorService();
$activityService = new ActivityLogService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $code = trim($_POST['code'] ?? '');
        $useBackupCode = isset($_POST['use_backup_code']);

        if (empty($code)) {
            $error = 'Please enter a verification code';
        } else {
            $verified = false;

            if ($useBackupCode) {
                // Verify backup code
                $verified = $twoFactorService->verifyBackupCode($userId, $code);
                if (!$verified) {
                    $error = 'Invalid backup code';
                    $activityService->log($userId, 'login_2fa_failed', 'Backup code verification failed');
                }
            } else {
                // Verify TOTP code
                $secret = $twoFactorService->getSecret($userId);
                $verified = $twoFactorService->verifyCode($secret, $code);
                if (!$verified) {
                    $error = 'Invalid verification code';
                    $activityService->log($userId, 'login_2fa_failed', 'TOTP verification failed');
                }
            }

            if ($verified) {
                // Get user data and complete login
                $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user) {
                    // Set session
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'active_ftp_connection_id' => $user['active_ftp_connection_id']
                    ];

                    // Clear 2FA session data
                    unset($_SESSION['2fa_user_id']);

                    // Update last login
                    $userService = new \App\Services\UserService();
                    $userService->updateLastLogin($user['id'], \App\Services\RateLimitService::getClientIP());

                    // Log successful login
                    $activityService->log($user['id'], 'login', 'Successful login with 2FA');

                    redirect('/browse.php');
                } else {
                    $error = 'User not found';
                }
            }
        }
    }
}

view('verify-2fa', [
    'error' => $error
]);
