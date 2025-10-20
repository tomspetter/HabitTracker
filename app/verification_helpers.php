<?php
/**
 * Verification Code Helpers
 *
 * Functions for generating and validating verification codes
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
 * @param string $type Type of code ('registration' or 'reset')
 * @param int $expiryMinutes How long the code is valid (default 15)
 * @return bool Success
 */
function storeVerificationCode($email, $code, $type = 'registration', $expiryMinutes = 15) {
    $codesFile = __DIR__ . '/data/verification_codes.json';

    // Load existing codes
    $codes = [];
    if (file_exists($codesFile)) {
        $codesData = file_get_contents($codesFile);
        $codes = json_decode($codesData, true) ?: [];
    }

    // Create code entry
    $codes[$email] = [
        'code' => $code,
        'type' => $type,
        'expires' => time() + ($expiryMinutes * 60),
        'attempts' => 0
    ];

    // Save codes
    return file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT)) !== false;
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
    $codesFile = __DIR__ . '/data/verification_codes.json';

    if (!file_exists($codesFile)) {
        return ['valid' => false, 'error' => 'No verification code found'];
    }

    $codesData = file_get_contents($codesFile);
    $codes = json_decode($codesData, true) ?: [];

    // Check if code exists for email
    if (!isset($codes[$email])) {
        return ['valid' => false, 'error' => 'No verification code found for this email'];
    }

    $storedData = $codes[$email];

    // Check if code type matches
    if ($storedData['type'] !== $type) {
        return ['valid' => false, 'error' => 'Invalid code type'];
    }

    // Check if code has expired
    if (time() > $storedData['expires']) {
        // Clean up expired code
        unset($codes[$email]);
        file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
        return ['valid' => false, 'error' => 'Verification code has expired'];
    }

    // Check attempts (max 5 attempts)
    if ($storedData['attempts'] >= 5) {
        return ['valid' => false, 'error' => 'Too many failed attempts. Please request a new code'];
    }

    // Verify the code
    if ($storedData['code'] !== $code) {
        // Increment attempts
        $codes[$email]['attempts']++;
        file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));

        $attemptsLeft = 5 - $codes[$email]['attempts'];
        return ['valid' => false, 'error' => "Invalid code. $attemptsLeft attempts remaining"];
    }

    // Code is valid - clean it up
    unset($codes[$email]);
    file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));

    return ['valid' => true, 'error' => null];
}

/**
 * Check if a code can be resent (rate limiting)
 *
 * @param string $email User's email
 * @return array ['canResend' => bool, 'waitSeconds' => int]
 */
function canResendCode($email) {
    $codesFile = __DIR__ . '/data/verification_codes.json';

    if (!file_exists($codesFile)) {
        return ['canResend' => true, 'waitSeconds' => 0];
    }

    $codesData = file_get_contents($codesFile);
    $codes = json_decode($codesData, true) ?: [];

    if (!isset($codes[$email])) {
        return ['canResend' => true, 'waitSeconds' => 0];
    }

    // Require 60 seconds between resend requests
    $timeSinceCreation = time() - ($codes[$email]['expires'] - (15 * 60));
    $waitTime = 60 - $timeSinceCreation;

    if ($waitTime > 0) {
        return ['canResend' => false, 'waitSeconds' => $waitTime];
    }

    return ['canResend' => true, 'waitSeconds' => 0];
}

/**
 * Get pending registration data for an email
 *
 * @param string $email User's email
 * @return array|null Registration data or null
 */
function getPendingRegistration($email) {
    $pendingFile = __DIR__ . '/data/pending_registrations.json';

    if (!file_exists($pendingFile)) {
        return null;
    }

    $pendingData = file_get_contents($pendingFile);
    $pending = json_decode($pendingData, true) ?: [];

    return $pending[$email] ?? null;
}

/**
 * Store pending registration data
 *
 * @param string $email User's email
 * @param string $passwordHash Hashed password
 * @return bool Success
 */
function storePendingRegistration($email, $passwordHash) {
    $pendingFile = __DIR__ . '/data/pending_registrations.json';

    // Load existing pending registrations
    $pending = [];
    if (file_exists($pendingFile)) {
        $pendingData = file_get_contents($pendingFile);
        $pending = json_decode($pendingData, true) ?: [];
    }

    // Store registration data
    $pending[$email] = [
        'password_hash' => $passwordHash,
        'created' => time()
    ];

    // Clean up old pending registrations (older than 24 hours)
    foreach ($pending as $pendingEmail => $data) {
        if (time() - $data['created'] > 86400) {
            unset($pending[$pendingEmail]);
        }
    }

    return file_put_contents($pendingFile, json_encode($pending, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Remove pending registration after successful verification
 *
 * @param string $email User's email
 * @return bool Success
 */
function removePendingRegistration($email) {
    $pendingFile = __DIR__ . '/data/pending_registrations.json';

    if (!file_exists($pendingFile)) {
        return true;
    }

    $pendingData = file_get_contents($pendingFile);
    $pending = json_decode($pendingData, true) ?: [];

    unset($pending[$email]);

    return file_put_contents($pendingFile, json_encode($pending, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Clear all verification codes for an email
 *
 * @param string $email User's email
 * @return bool Success
 */
function clearVerificationCodes($email) {
    $codesFile = __DIR__ . '/data/verification_codes.json';

    if (!file_exists($codesFile)) {
        return true;
    }

    $codesData = file_get_contents($codesFile);
    $codes = json_decode($codesData, true) ?: [];

    unset($codes[$email]);

    return file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT)) !== false;
}
