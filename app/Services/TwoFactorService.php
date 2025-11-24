<?php

namespace App\Services;

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Service for Two-Factor Authentication (2FA)
 *
 * Handles TOTP generation, verification, QR code generation,
 * and backup codes for enhanced account security.
 */
class TwoFactorService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Generate a new 2FA secret for a user
     *
     * @param string $userEmail User's email for TOTP label
     * @return string The generated secret key
     */
    public function generateSecret(string $userEmail): string
    {
        $totp = TOTP::create();
        $totp->setLabel($userEmail);
        $totp->setIssuer(env('APP_NAME', 'Dora Files'));

        return $totp->getSecret();
    }

    /**
     * Get QR code URI for authenticator apps
     *
     * @param string $secret TOTP secret
     * @param string $userEmail User's email for label
     * @return string Provisioning URI for QR code
     */
    public function getQRCodeUri(string $secret, string $userEmail): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($userEmail);
        $totp->setIssuer(env('APP_NAME', 'Dora Files'));

        return $totp->getProvisioningUri();
    }

    /**
     * Generate QR code as base64 data URI
     *
     * @param string $secret TOTP secret
     * @param string $userEmail User's email for label
     * @return string Base64-encoded QR code data URI
     */
    public function getQRCodeBase64(string $secret, string $userEmail): string
    {
        $uri = $this->getQRCodeUri($secret, $userEmail);

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
     *
     * @param string $secret TOTP secret
     * @param string $code Code to verify
     * @return bool True if code is valid
     */
    public function verifyCode(string $secret, string $code): bool
    {
        if (empty($secret) || empty($code)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);

        // Verify with a window of +/-1 period (30 seconds before/after)
        return $totp->verify($code, null, 1);
    }

    /**
     * Enable 2FA for a user
     *
     * @param int $userId User ID
     * @param string $secret TOTP secret to store
     * @return bool True on success
     */
    public function enable(int $userId, string $secret): bool
    {
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
     *
     * @param int $userId User ID
     * @return bool True on success
     */
    public function disable(int $userId): bool
    {
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
     *
     * @param int $userId User ID
     * @return bool True if 2FA is enabled
     */
    public function isEnabled(int $userId): bool
    {
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
     * Get user's 2FA secret (decrypted)
     *
     * @param int $userId User ID
     * @return string|null Decrypted secret or null if not set
     */
    public function getSecret(int $userId): ?string
    {
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
     * Generate backup codes for account recovery
     *
     * @param int $count Number of codes to generate
     * @return array Array of plain text backup codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate 8-character alphanumeric code
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        return $codes;
    }

    /**
     * Save backup codes for a user (hashed)
     *
     * @param int $userId User ID
     * @param array $codes Plain text backup codes
     * @return bool True on success
     */
    public function saveBackupCodes(int $userId, array $codes): bool
    {
        // Hash each code before storing
        $hashedCodes = array_map(function ($code) {
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
     *
     * @param int $userId User ID
     * @param string $code Backup code to verify
     * @return bool True if code was valid and consumed
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
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
     *
     * @param int $userId User ID
     * @return int Number of remaining backup codes
     */
    public function getRemainingBackupCodesCount(int $userId): int
    {
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
