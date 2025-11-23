<?php

require_once __DIR__ . '/vendor/autoload.php';

loadEnv();

// Apply security
require_once __DIR__ . '/app/init_security.php';

// Check authentication
if (!isAuthenticated()) {
    redirect('/login.php');
}

use App\Services\UserService;
use App\Services\FTPConnectionService;
use App\Services\ActivityLogService;
use App\Services\TwoFactorService;

$user = auth();
$userService = new UserService();
$ftpService = new FTPConnectionService();
$activityService = new ActivityLogService();
$twoFactorService = new TwoFactorService();

// Get current tab
$tab = $_GET['tab'] ?? 'overview';
$allowedTabs = ['overview', 'ftp-connections', 'activity', 'settings'];
if (!in_array($tab, $allowedTabs)) {
    $tab = 'overview';
}

$error = null;
$success = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !csrf_verify($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_email':
                $newEmail = $_POST['email'] ?? '';
                $result = $userService->updateEmail($user['id'], $newEmail);

                if ($result === true) {
                    $success = 'Email updated successfully';
                    // Update session
                    $_SESSION['user']['email'] = $newEmail;
                } else {
                    $error = $result;
                }
                break;

            case 'update_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match';
                } else {
                    $result = $userService->updatePassword($user['id'], $currentPassword, $newPassword);

                    if ($result === true) {
                        $success = 'Password updated successfully';
                    } else {
                        $error = $result;
                    }
                }
                break;

            case 'create_ftp_connection':
                $connectionName = $_POST['connection_name'] ?? '';
                $ftpHost = $_POST['ftp_host'] ?? '';
                $ftpPort = (int)($_POST['ftp_port'] ?? 21);
                $ftpUsername = $_POST['ftp_username'] ?? '';
                $ftpPassword = $_POST['ftp_password'] ?? '';
                $ftpBasePath = $_POST['ftp_base_path'] ?? '/';
                $isDefault = isset($_POST['is_default']);

                $result = $ftpService->createConnection(
                    $user['id'],
                    $connectionName,
                    $ftpHost,
                    $ftpPort,
                    $ftpUsername,
                    $ftpPassword,
                    $ftpBasePath,
                    $isDefault
                );

                if (is_numeric($result)) {
                    $success = 'FTP connection created successfully';
                    $tab = 'ftp-connections';
                } else {
                    $error = $result;
                }
                break;

            case 'update_ftp_connection':
                $connectionId = (int)($_POST['connection_id'] ?? 0);
                $connectionName = $_POST['connection_name'] ?? '';
                $ftpHost = $_POST['ftp_host'] ?? '';
                $ftpPort = (int)($_POST['ftp_port'] ?? 21);
                $ftpUsername = $_POST['ftp_username'] ?? '';
                $ftpPassword = $_POST['ftp_password'] ?? ''; // Can be empty to keep existing
                $ftpBasePath = $_POST['ftp_base_path'] ?? '/';
                $isDefault = isset($_POST['is_default']);

                $result = $ftpService->updateConnection(
                    $connectionId,
                    $user['id'],
                    $connectionName,
                    $ftpHost,
                    $ftpPort,
                    $ftpUsername,
                    $ftpPassword,
                    $ftpBasePath,
                    $isDefault
                );

                if ($result === true) {
                    $success = 'FTP connection updated successfully';
                    $tab = 'ftp-connections';
                } else {
                    $error = $result;
                }
                break;

            case 'delete_ftp_connection':
                $connectionId = (int)($_POST['connection_id'] ?? 0);
                $result = $ftpService->deleteConnection($connectionId, $user['id']);

                if ($result === true) {
                    $success = 'FTP connection deleted successfully';
                    $tab = 'ftp-connections';
                } else {
                    $error = $result;
                }
                break;

            case 'switch_ftp_connection':
                $connectionId = (int)($_POST['connection_id'] ?? 0);
                $result = $ftpService->switchConnection($connectionId, $user['id']);

                if ($result === true) {
                    $success = 'Switched to selected FTP connection';
                    redirect('/profile.php?tab=ftp-connections&success=switched');
                } else {
                    $error = $result;
                }
                break;

            case 'enable_2fa':
                $code = $_POST['code'] ?? '';
                $secret = $_SESSION['2fa_setup_secret'] ?? '';

                if (empty($secret)) {
                    $error = 'No 2FA setup in progress';
                } elseif (empty($code)) {
                    $error = 'Please enter the verification code';
                } elseif (!$twoFactorService->verifyCode($secret, $code)) {
                    $error = 'Invalid verification code. Please try again.';
                } else {
                    // Enable 2FA
                    $twoFactorService->enable($user['id'], $secret);

                    // Generate backup codes
                    $backupCodes = $twoFactorService->generateBackupCodes();
                    $twoFactorService->saveBackupCodes($user['id'], $backupCodes);

                    // Store backup codes in session to display once
                    $_SESSION['2fa_backup_codes'] = $backupCodes;

                    unset($_SESSION['2fa_setup_secret']);
                    $success = '2FA enabled successfully';
                    $tab = 'settings';
                }
                break;

            case 'disable_2fa':
                $password = $_POST['password'] ?? '';

                // Verify password before disabling
                if (!$userService->verifyPassword($user['id'], $password)) {
                    $error = 'Invalid password';
                } else {
                    $twoFactorService->disable($user['id']);
                    $success = '2FA disabled successfully';
                    $tab = 'settings';
                }
                break;

            case 'regenerate_backup_codes':
                $password = $_POST['password'] ?? '';

                // Verify password before regenerating
                if (!$userService->verifyPassword($user['id'], $password)) {
                    $error = 'Invalid password';
                } else {
                    $backupCodes = $twoFactorService->generateBackupCodes();
                    $twoFactorService->saveBackupCodes($user['id'], $backupCodes);
                    $_SESSION['2fa_backup_codes'] = $backupCodes;
                    $success = 'Backup codes regenerated successfully';
                    $tab = 'settings';
                }
                break;

            case 'delete_account':
                $password = $_POST['password'] ?? '';
                $result = $userService->deleteAccount($user['id'], $password);

                if ($result === true) {
                    // Logout and redirect
                    $authService = new \App\Services\AuthService();
                    $authService->logout();
                    redirect('/login.php?message=account_deleted');
                } else {
                    $error = $result;
                }
                break;
        }
    }
}

// Get profile data
$profile = $userService->getProfile($user['id']);
$ftpConnections = $ftpService->getUserConnections($user['id']);

// Get activity data if on activity tab
$activityData = null;
if ($tab === 'activity') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $activityData = $activityService->getUserActivity($user['id'], $page, 20);
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success = 'Operation completed successfully';
}

// Setup 2FA data
$twoFactorEnabled = $twoFactorService->isEnabled($user['id']);
$twoFactorSecret = null;
$twoFactorQRCode = null;
$backupCodes = $_SESSION['2fa_backup_codes'] ?? null;
$remainingBackupCodes = $twoFactorService->getRemainingBackupCodesCount($user['id']);

// Generate new secret if setting up
if (isset($_GET['setup_2fa']) && $_GET['setup_2fa'] === '1' && !$twoFactorEnabled) {
    $twoFactorSecret = $twoFactorService->generateSecret($user['email']);
    $twoFactorQRCode = $twoFactorService->getQRCodeBase64($twoFactorSecret, $user['email']);
    $_SESSION['2fa_setup_secret'] = $twoFactorSecret;
    $tab = 'settings'; // Force settings tab
}

// Clear backup codes from session after displaying
if ($backupCodes && $tab !== 'settings') {
    unset($_SESSION['2fa_backup_codes']);
    $backupCodes = null;
}

view('profile/index', [
    'user' => $user,
    'profile' => $profile,
    'tab' => $tab,
    'error' => $error,
    'success' => $success,
    'ftpConnections' => $ftpConnections,
    'activityData' => $activityData,
    'twoFactorEnabled' => $twoFactorEnabled,
    'twoFactorSecret' => $twoFactorSecret,
    'twoFactorQRCode' => $twoFactorQRCode,
    'backupCodes' => $backupCodes,
    'remainingBackupCodes' => $remainingBackupCodes,
]);
