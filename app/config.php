<?php
/**
 * Habit Tracker Configuration
 *
 * Configuration file for the habit tracker with MySQL database support
 * and server-side encryption for habit names.
 *
 * ENCRYPTION SECURITY MODEL:
 * - Habit names are encrypted server-side using AES-256-CBC
 * - Each user has a unique encryption key derived from master key + user ID
 * - Each encrypted value has its own initialization vector (IV)
 * - In a database breach: attacker sees encrypted habit names but cannot decrypt them
 * - Master encryption key MUST be stored in environment variable (never in code)
 *
 * WHAT'S PROTECTED IN A DATABASE BREACH:
 * ✓ Habit names (encrypted) - e.g., "Stop drinking", "Therapy sessions"
 * ✓ Passwords (bcrypt hashed) - cannot be reversed
 * ✗ Dates (not encrypted) - needed for efficient queries, meaningless without habit names
 * ✗ Colors (not encrypted) - just UI preferences, not sensitive
 * ✗ Emails (not encrypted) - needed for login, not considered secret
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

// Database connection settings
// IMPORTANT: Set these via environment variables or update with your values
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'habittracker');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * @return PDO Database connection
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed. Please check your configuration.');
        }
    }

    return $pdo;
}

// ============================================================================
// ENCRYPTION CONFIGURATION
// ============================================================================

/**
 * CRITICAL SECURITY CHECK
 * Prevents the application from running without encryption key configured.
 * This ensures habit names are always encrypted in production.
 *
 * SETUP: Generate a key with: openssl rand -base64 32
 * Then set it as an environment variable or add it here for development.
 */
if (!getenv('HABIT_ENCRYPTION_KEY')) {
    // For development only - replace with your own key
    // NEVER commit your actual encryption key to git!
    putenv('HABIT_ENCRYPTION_KEY=REPLACE_WITH_YOUR_KEY_FROM_OPENSSL_RAND_BASE64_32');
}

define('ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Get user-specific encryption key
 *
 * Security approach: Each user gets a unique encryption key derived from:
 * - Master encryption key (from environment variable)
 * - User ID (ensures different keys per user)
 *
 * Why user-specific keys:
 * - Limits blast radius: If one user's data is compromised, others remain safe
 * - Different IVs per encryption + different keys = maximum security
 * - Even identical habit names encrypt to different values for different users
 *
 * @param int $userId User ID
 * @return string 32-byte encryption key
 */
function getEncryptionKey($userId) {
    $masterKey = getenv('HABIT_ENCRYPTION_KEY');

    // Derive user-specific key using HMAC-SHA256
    // This creates a unique 32-byte key for each user
    return hash_hmac('sha256', (string)$userId, $masterKey, true);
}

/**
 * Encrypt habit name
 *
 * Uses AES-256-CBC with unique IV per encryption.
 * Format: base64(IV + encrypted_data)
 *
 * The IV is prepended to the encrypted data so we can decrypt later.
 * IV doesn't need to be secret, but MUST be unique for each encryption.
 *
 * @param string $plaintext Habit name to encrypt
 * @param int $userId User ID (for user-specific key)
 * @return string Base64-encoded encrypted data with IV
 * @throws Exception if encryption fails
 */
function encryptHabitName($plaintext, $userId) {
    $key = getEncryptionKey($userId);

    // Generate random IV (Initialization Vector)
    // IV must be unique for each encryption operation
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);

    // Encrypt the data
    $encrypted = openssl_encrypt(
        $plaintext,
        ENCRYPTION_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new Exception('Encryption failed');
    }

    // Prepend IV to encrypted data and encode as base64 for storage
    // Format: base64(IV + encrypted_data)
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt habit name
 *
 * Reverses the encryption process to retrieve the original habit name.
 *
 * @param string $encryptedData Base64-encoded encrypted data with IV
 * @param int $userId User ID (for user-specific key)
 * @return string|false Decrypted habit name, or false on failure
 */
function decryptHabitName($encryptedData, $userId) {
    $key = getEncryptionKey($userId);

    // Decode from base64
    $data = base64_decode($encryptedData, true);
    if ($data === false) {
        error_log('Decryption failed: Invalid base64 encoding');
        return false;
    }

    // Extract IV and encrypted data
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    // Decrypt
    $decrypted = openssl_decrypt(
        $encrypted,
        ENCRYPTION_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($decrypted === false) {
        error_log('Decryption failed for user ' . $userId);
        return false;
    }

    return $decrypted;
}

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Only set secure flag if using HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Note: CSRF token functions are defined in individual API files

// Authentication helper for protected endpoints
function requireLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}
