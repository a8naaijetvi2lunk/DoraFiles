<?php

namespace App\Services;

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorService {
    private $pdo;

    public function __construct() {
        $this->pdo = db();
    }

    /**
     * Generate a new 2FA secret for a user
     */
    public function generateSecret($userEmail) {
        $totp = TOTP::create();
        $totp->setLabel($userEmail);
        $totp->setIssuer(env('APP_NAME', 'Dora Files'));

        return $totp->getSecret();
    }

    /**
     * Get QR code URI for Google Authenticator
     */
    public function getQRCodeUri($secret, $userEmail) {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($userEmail);
        $totp->setIssuer(env('APP_NAME', 'Dora Files'));

        return $totp->getProvisioningUri();
    }

    /**
     * Generate QR code as base64 data URI
     */
    public function getQRCodeBase64($secret, $userEmail) {
        $uri = $this->getQRCodeUri($secret, $userEmail);

        // Create QR code - v6 uses constructor with named parameters
        $qrCode = new QrCode(
            data: $uri,
            size: 250,
            margin: 10
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getDataUri();
    }

    /**
     * Verify a TOTP code
     */
    public function verifyCode($secret, $code) {
        if (empty($secret) || empty($code)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);

        // Verify with a window of ±1 period (30 seconds before/after)
        return $totp->verify($code, null, 1);
    }

    /**
     * Enable 2FA for a user
     */
    public function enable($userId, $secret) {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_secret = ?,
                two_factor_enabled = 1,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([encrypt($secret), $userId]);
    }

    /**
     * Disable 2FA for a user
     */
    public function disable($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_secret = NULL,
                two_factor_enabled = 0,
                two_factor_backup_codes = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$userId]);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isEnabled($userId) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_enabled
            FROM users
            WHERE id = ?
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        return $result && $result['two_factor_enabled'] == 1;
    }

    /**
     * Get user's 2FA secret
     */
    public function getSecret($userId) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_secret
            FROM users
            WHERE id = ?
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if ($result && !empty($result['two_factor_secret'])) {
            return decrypt($result['two_factor_secret']);
        }

        return null;
    }

    /**
     * Generate backup codes
     */
    public function generateBackupCodes($count = 8) {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate 8-character alphanumeric code
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        return $codes;
    }

    /**
     * Save backup codes for a user
     */
    public function saveBackupCodes($userId, array $codes) {
        // Hash each code before storing
        $hashedCodes = array_map(function($code) {
            return password_hash($code, PASSWORD_BCRYPT);
        }, $codes);

        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_backup_codes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([json_encode($hashedCodes), $userId]);
    }

    /**
     * Verify and consume a backup code
     */
    public function verifyBackupCode($userId, $code) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_backup_codes
            FROM users
            WHERE id = ?
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if (!$result || empty($result['two_factor_backup_codes'])) {
            return false;
        }

        $hashedCodes = json_decode($result['two_factor_backup_codes'], true);

        if (!is_array($hashedCodes)) {
            return false;
        }

        // Check each hashed code
        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                // Remove the used code
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes);

                // Update database
                $updateStmt = $this->pdo->prepare("
                    UPDATE users
                    SET two_factor_backup_codes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $updateStmt->execute([json_encode($hashedCodes), $userId]);

                return true;
            }
        }

        return false;
    }

    /**
     * Count remaining backup codes
     */
    public function getRemainingBackupCodesCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_backup_codes
            FROM users
            WHERE id = ?
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if (!$result || empty($result['two_factor_backup_codes'])) {
            return 0;
        }

        $hashedCodes = json_decode($result['two_factor_backup_codes'], true);

        return is_array($hashedCodes) ? count($hashedCodes) : 0;
    }
}
