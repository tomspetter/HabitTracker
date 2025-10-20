<?php
/**
 * Habit Tracker Configuration
 *
 * Simple configuration file for the habit tracker.
 * Modify these settings based on your server setup.
 */

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// File paths
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('LOGIN_ATTEMPTS_FILE', DATA_DIR . '/login_attempts.json');

// Ensure data directory exists and is writable
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Initialize users file if it doesn't exist
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([], JSON_PRETTY_PRINT));
}

// Initialize login attempts file if it doesn't exist
if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
    file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode([], JSON_PRETTY_PRINT));
}

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

// CSRF token generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Require login (use in protected endpoints)
function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}
