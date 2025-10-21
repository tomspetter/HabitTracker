<?php
/**
 * Verification Code Helpers (MySQL Version)
 *
 * Functions for generating and validating verification codes using MySQL database
 */

/**
 * Generate a 6-digit verification code
 *
 * @return string 6-digit code
 */
function generateVerificationCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Store verification code for email
 *
 * @param string $email User's email
 * @param string $code Verification code
 * @param string $type Type of code ('registration', 'password_reset', or 'reset_token')
 * @param int $expiryMinutes How long the code is valid (default 15)
 * @return bool Success
 */
function storeVerificationCode($email, $code, $type = 'registration', $expiryMinutes = 15) {
    try {
        $pdo = getDBConnection();

        // Clear any existing codes of this type for this email
        $stmt = $pdo->prepare("
            DELETE FROM verification_codes
            WHERE email = :email AND code_type = :type
        ");
        $stmt->execute([
            ':email' => $email,
            ':type' => $type
        ]);

        // Insert new code
        $stmt = $pdo->prepare("
            INSERT INTO verification_codes (email, code, code_type, created_at, expires_at)
            VALUES (:email, :code, :type, NOW(), DATE_ADD(NOW(), INTERVAL :minutes MINUTE))
        ");

        $stmt->execute([
            ':email' => $email,
            ':code' => $code,
            ':type' => $type,
            ':minutes' => $expiryMinutes
        ]);

        return true;

    } catch (PDOException $e) {
        error_log('Error storing verification code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify a code for an email
 *
 * @param string $email User's email
 * @param string $code Code to verify
 * @param string $type Type of code to verify
 * @return array ['valid' => bool, 'error' => string|null]
 */
function verifyCode($email, $code, $type = 'registration') {
    try {
        $pdo = getDBConnection();

        // Clean up expired codes first
        $pdo->exec("DELETE FROM verification_codes WHERE expires_at < NOW()");

        // Find matching code that hasn't expired (using MySQL NOW() for consistent timezone)
        $stmt = $pdo->prepare("
            SELECT id, code, expires_at
            FROM verification_codes
            WHERE email = :email
              AND code_type = :type
              AND used = FALSE
              AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email,
            ':type' => $type
        ]);

        $codeData = $stmt->fetch();

        if (!$codeData) {
            return ['valid' => false, 'error' => 'Verification code has expired or not found'];
        }

        // Verify the code (constant-time comparison for security)
        if (!hash_equals($codeData['code'], $code)) {
            return ['valid' => false, 'error' => 'Invalid verification code'];
        }

        // Mark code as used
        $pdo->prepare("UPDATE verification_codes SET used = TRUE WHERE id = :id")
            ->execute([':id' => $codeData['id']]);

        return ['valid' => true, 'error' => null];

    } catch (PDOException $e) {
        error_log('Error verifying code: ' . $e->getMessage());
        return ['valid' => false, 'error' => 'An error occurred. Please try again.'];
    }
}

/**
 * Check if a code can be resent (rate limiting)
 *
 * @param string $email User's email
 * @return array ['canResend' => bool, 'waitSeconds' => int]
 */
function canResendCode($email) {
    try {
        $pdo = getDBConnection();

        // Find most recent code for this email
        $stmt = $pdo->prepare("
            SELECT created_at
            FROM verification_codes
            WHERE email = :email
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([':email' => $email]);
        $codeData = $stmt->fetch();

        if (!$codeData) {
            return ['canResend' => true, 'waitSeconds' => 0];
        }

        // Require 60 seconds between resend requests
        $timeSinceCreation = time() - strtotime($codeData['created_at']);
        $waitTime = 60 - $timeSinceCreation;

        if ($waitTime > 0) {
            return ['canResend' => false, 'waitSeconds' => $waitTime];
        }

        return ['canResend' => true, 'waitSeconds' => 0];

    } catch (PDOException $e) {
        error_log('Error checking resend eligibility: ' . $e->getMessage());
        // Allow resend on error to not block users
        return ['canResend' => true, 'waitSeconds' => 0];
    }
}

/**
 * Get pending registration data for an email
 *
 * @param string $email User's email
 * @return array|null Registration data or null
 */
function getPendingRegistration($email) {
    try {
        $pdo = getDBConnection();

        // Clean up expired registrations first
        $pdo->exec("DELETE FROM pending_registrations WHERE expires_at < NOW()");

        $stmt = $pdo->prepare("
            SELECT email, password_hash, created_at, expires_at
            FROM pending_registrations
            WHERE email = :email
              AND expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([':email' => $email]);
        $data = $stmt->fetch();

        return $data ?: null;

    } catch (PDOException $e) {
        error_log('Error getting pending registration: ' . $e->getMessage());
        return null;
    }
}

/**
 * Store pending registration data
 *
 * @param string $email User's email
 * @param string $passwordHash Hashed password
 * @param int $expiryMinutes How long the registration is valid (default 30)
 * @return bool Success
 */
function storePendingRegistration($email, $passwordHash, $expiryMinutes = 30) {
    try {
        $pdo = getDBConnection();

        // Delete any existing pending registration for this email
        $stmt = $pdo->prepare("DELETE FROM pending_registrations WHERE email = :email");
        $stmt->execute([':email' => $email]);

        // Insert new pending registration
        $stmt = $pdo->prepare("
            INSERT INTO pending_registrations (email, password_hash, created_at, expires_at)
            VALUES (:email, :password_hash, NOW(), DATE_ADD(NOW(), INTERVAL :minutes MINUTE))
        ");

        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':minutes' => $expiryMinutes
        ]);

        return true;

    } catch (PDOException $e) {
        error_log('Error storing pending registration: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove pending registration after successful verification
 *
 * @param string $email User's email
 * @return bool Success
 */
function removePendingRegistration($email) {
    try {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("DELETE FROM pending_registrations WHERE email = :email");
        $stmt->execute([':email' => $email]);

        return true;

    } catch (PDOException $e) {
        error_log('Error removing pending registration: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clear all verification codes for an email
 *
 * @param string $email User's email
 * @return bool Success
 */
function clearVerificationCodes($email) {
    try {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE email = :email");
        $stmt->execute([':email' => $email]);

        return true;

    } catch (PDOException $e) {
        error_log('Error clearing verification codes: ' . $e->getMessage());
        return false;
    }
}
