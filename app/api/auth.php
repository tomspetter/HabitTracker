<?php
/**
 * Authentication API (MySQL Version)
 *
 * Handles user registration with email verification, login, logout, and session management.
 * Uses MySQL database instead of JSON files.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../verification_helpers.php';
require_once __DIR__ . '/../email_service.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Find user by email
 */
function getUserByEmail($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

/**
 * Check login attempts and enforce rate limiting
 */
function checkLoginAttempts($email) {
    $pdo = getDBConnection();

    // Clean up old attempts first
    $pdo->exec("DELETE FROM login_attempts WHERE locked_until < NOW()");

    $stmt = $pdo->prepare("
        SELECT attempt_count, locked_until
        FROM login_attempts
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $attempts = $stmt->fetch();

    if (!$attempts) {
        return true; // No attempts recorded, allow login
    }

    // Check if still locked
    if ($attempts['locked_until'] && strtotime($attempts['locked_until']) > time()) {
        return false;
    }

    // Check if too many attempts
    return $attempts['attempt_count'] < MAX_LOGIN_ATTEMPTS;
}

/**
 * Record login attempt
 */
function recordLoginAttempt($email, $success) {
    $pdo = getDBConnection();

    if ($success) {
        // Clear login attempts on successful login
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = :email");
        $stmt->execute([':email' => $email]);
    } else {
        // Increment failed attempts
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, attempt_count, last_attempt_at, locked_until)
            VALUES (:email, 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                attempt_count = attempt_count + 1,
                last_attempt_at = NOW(),
                locked_until = IF(attempt_count + 1 >= :max_attempts,
                    DATE_ADD(NOW(), INTERVAL :lockout_seconds SECOND),
                    NULL)
        ");
        $stmt->execute([
            ':email' => $email,
            ':max_attempts' => MAX_LOGIN_ATTEMPTS,
            ':lockout_seconds' => LOGIN_LOCKOUT_TIME
        ]);
    }
}

/**
 * Start secure session
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ============================================================================
// REGISTER - Step 1: Send verification code
// ============================================================================

if ($action === 'register') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }

    // Validate password length
    if (strlen($password) < 8) {
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        exit;
    }

    // Check if user already exists
    if (getUserByEmail($email) !== false) {
        echo json_encode(['error' => 'An account with this email already exists']);
        exit;
    }

    // Check if email feature is enabled
    if (!EMAIL_ENABLED) {
        echo json_encode(['error' => 'Email verification is not configured. Please contact the administrator.']);
        exit;
    }

    // Generate verification code
    $verificationCode = generateVerificationCode();

    // Hash password and store pending registration
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    storePendingRegistration($email, $passwordHash);

    // Store verification code
    storeVerificationCode($email, $verificationCode, 'registration', 15);

    // Send verification email
    $emailResult = sendVerificationCodeEmail($email, $verificationCode);

    if (!$emailResult['success']) {
        echo json_encode(['error' => 'Failed to send verification email: ' . $emailResult['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email',
        'email' => $email
    ]);
    exit;
}

// ============================================================================
// VERIFY - Step 2: Verify code and complete registration
// ============================================================================

if ($action === 'verify') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');

    if (empty($email) || empty($code)) {
        echo json_encode(['error' => 'Email and verification code are required']);
        exit;
    }

    // Verify the code
    $verification = verifyCode($email, $code, 'registration');

    if (!$verification['valid']) {
        echo json_encode(['error' => $verification['error']]);
        exit;
    }

    // Get pending registration data
    $pendingData = getPendingRegistration($email);

    if (!$pendingData) {
        echo json_encode(['error' => 'No pending registration found. Please register again.']);
        exit;
    }

    try {
        $pdo = getDBConnection();

        // Create user account
        $userHash = md5($email);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, user_hash, email_verified, created_at)
            VALUES (:email, :password_hash, :user_hash, TRUE, NOW())
        ");

        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $pendingData['password_hash'],
            ':user_hash' => $userHash
        ]);

        $userId = $pdo->lastInsertId();

        // Remove pending registration
        removePendingRegistration($email);

        // Create session
        startSecureSession();
        $_SESSION['user_hash'] = $userHash;
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully',
            'user' => [
                'email' => $email,
                'user_hash' => $userHash
            ]
        ]);

    } catch (PDOException $e) {
        error_log('Registration error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create account. Please try again.']);
    }

    exit;
}

// ============================================================================
// RESEND - Resend verification code
// ============================================================================

if ($action === 'resend') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    // Check if can resend
    $resendCheck = canResendCode($email);
    if (!$resendCheck['canResend']) {
        echo json_encode([
            'error' => 'Please wait ' . $resendCheck['waitSeconds'] . ' seconds before requesting a new code'
        ]);
        exit;
    }

    // Check if there's a pending registration
    $pendingData = getPendingRegistration($email);
    if (!$pendingData) {
        echo json_encode(['error' => 'No pending registration found']);
        exit;
    }

    // Generate new verification code
    $verificationCode = generateVerificationCode();

    // Store new verification code
    storeVerificationCode($email, $verificationCode, 'registration', 15);

    // Send verification email
    $emailResult = sendVerificationCodeEmail($email, $verificationCode);

    if (!$emailResult['success']) {
        echo json_encode(['error' => 'Failed to send verification email: ' . $emailResult['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'New verification code sent to your email'
    ]);
    exit;
}

// ============================================================================
// LOGIN
// ============================================================================

if ($action === 'login') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    // Check login attempts
    if (!checkLoginAttempts($email)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many login attempts. Please try again in 15 minutes.']);
        exit;
    }

    // Find user
    $user = getUserByEmail($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($email, false);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    // Successful login
    recordLoginAttempt($email, true);

    // Create session
    startSecureSession();
    $_SESSION['user_hash'] = $user['user_hash'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['last_activity'] = time();

    echo json_encode([
        'success' => true,
        'user' => [
            'email' => $user['email'],
            'user_hash' => $user['user_hash']
        ]
    ]);
    exit;
}

// ============================================================================
// LOGOUT
// ============================================================================

if ($action === 'logout') {
    session_start();
    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}

// ============================================================================
// CHECK SESSION
// ============================================================================

if ($action === 'check') {
    startSecureSession();

    if (isset($_SESSION['user_hash']) && isset($_SESSION['email'])) {
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_destroy();
            echo json_encode(['loggedIn' => false]);
            exit;
        }

        $_SESSION['last_activity'] = time();

        // Generate CSRF token if not present
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        echo json_encode([
            'loggedIn' => true,
            'user' => [
                'email' => $_SESSION['email'],
                'user_hash' => $_SESSION['user_hash']
            ],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
    exit;
}

// ============================================================================
// FORGOT PASSWORD - Step 1: Send reset code
// ============================================================================

if ($action === 'forgot-password') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }

    // Check if email feature is enabled
    if (!EMAIL_ENABLED) {
        echo json_encode(['error' => 'Password reset is not available. Please contact the administrator.']);
        exit;
    }

    // Check if user exists (but don't reveal if they don't for security)
    $user = getUserByEmail($email);

    // Always show success message to prevent email enumeration attacks
    // Only send email if user actually exists
    if ($user !== false) {
        // Generate reset code
        $resetCode = generateVerificationCode();

        // Store verification code
        storeVerificationCode($email, $resetCode, 'password_reset', 15);

        // Send reset email
        $emailResult = sendPasswordResetEmail($email, $resetCode);

        // Log error but don't reveal to user
        if (!$emailResult['success']) {
            error_log('Failed to send password reset email to ' . $email . ': ' . $emailResult['error']);
        }
    }

    // Always return success to prevent user enumeration
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, a password reset code has been sent'
    ]);
    exit;
}

// ============================================================================
// VERIFY RESET CODE - Step 2: Verify the reset code
// ============================================================================

if ($action === 'verify-reset-code') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');

    if (empty($email) || empty($code)) {
        echo json_encode(['error' => 'Email and reset code are required']);
        exit;
    }

    // Verify the code
    $verification = verifyCode($email, $code, 'password_reset');

    if (!$verification['valid']) {
        echo json_encode(['error' => $verification['error']]);
        exit;
    }

    // Code is valid - generate a temporary token for the password reset form
    $resetToken = bin2hex(random_bytes(32));

    // Store the reset token temporarily (reuse verification code storage with short expiry)
    $stored = storeVerificationCode($email, $resetToken, 'reset_token', 15); // 15 minute window to set new password

    if (!$stored) {
        error_log("Failed to store reset_token for $email");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate reset token. Please try again.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Code verified',
        'reset_token' => $resetToken
    ]);
    exit;
}

// ============================================================================
// RESET PASSWORD - Step 3: Set new password
// ============================================================================

if ($action === 'reset-password') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');
    $resetToken = trim($input['reset_token'] ?? '');
    $newPassword = $input['new_password'] ?? '';

    if (empty($email) || empty($resetToken) || empty($newPassword)) {
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }

    // Validate password length
    if (strlen($newPassword) < 8) {
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        exit;
    }

    // Verify the reset token
    $verification = verifyCode($email, $resetToken, 'reset_token');

    if (!$verification['valid']) {
        // Log the specific error for debugging
        error_log("Password reset failed for $email: " . ($verification['error'] ?? 'Unknown error'));
        echo json_encode(['error' => 'Invalid or expired reset token. Please start over.']);
        exit;
    }

    // Find user
    $user = getUserByEmail($email);

    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    try {
        // Update password
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE email = :email
        ");

        $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':email' => $email
        ]);

        // Clear all verification codes for this email
        clearVerificationCodes($email);

        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);

    } catch (PDOException $e) {
        error_log('Password reset error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password. Please try again.']);
    }

    exit;
}

// ============================================================================
// RESEND RESET CODE
// ============================================================================

if ($action === 'resend-reset-code') {
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !verifyCsrfToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    // Check if can resend
    $resendCheck = canResendCode($email);
    if (!$resendCheck['canResend']) {
        echo json_encode([
            'error' => 'Please wait ' . $resendCheck['waitSeconds'] . ' seconds before requesting a new code'
        ]);
        exit;
    }

    // Check if user exists (but don't reveal if they don't)
    $user = getUserByEmail($email);

    if ($user !== false) {
        // Generate new reset code
        $resetCode = generateVerificationCode();

        // Store new verification code
        storeVerificationCode($email, $resetCode, 'password_reset', 15);

        // Send reset email
        $emailResult = sendPasswordResetEmail($email, $resetCode);

        if (!$emailResult['success']) {
            error_log('Failed to resend password reset email to ' . $email . ': ' . $emailResult['error']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, a new reset code has been sent'
    ]);
    exit;
}

// ============================================================================
// GET CSRF TOKEN
// ============================================================================

if ($action === 'csrf') {
    $token = generateCsrfToken();
    echo json_encode(['csrf_token' => $token]);
    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
