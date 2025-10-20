<?php
/**
 * Authentication API (Email-Based)
 *
 * Handles user registration with email verification, login, logout, and session management.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../verification_helpers.php';
require_once __DIR__ . '/../email_service.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// Helper function to read users
function getUsers() {
    $json = file_get_contents(USERS_FILE);
    return json_decode($json, true) ?: [];
}

// Helper function to save users
function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// Helper function to find user by email
function getUserByEmail($email) {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

// Helper function to check login attempts
function checkLoginAttempts($email) {
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
        return true;
    }

    $attempts = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: [];

    if (!isset($attempts[$email])) {
        return true;
    }

    $userAttempts = $attempts[$email];

    // Clean old attempts
    if (time() - $userAttempts['timestamp'] > LOGIN_LOCKOUT_TIME) {
        unset($attempts[$email]);
        file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT));
        return true;
    }

    return $userAttempts['count'] < MAX_LOGIN_ATTEMPTS;
}

// Helper function to record login attempt
function recordLoginAttempt($email, $success) {
    $attempts = [];
    if (file_exists(LOGIN_ATTEMPTS_FILE)) {
        $attempts = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: [];
    }

    if ($success) {
        unset($attempts[$email]);
    } else {
        if (!isset($attempts[$email])) {
            $attempts[$email] = ['count' => 0, 'timestamp' => time()];
        }
        $attempts[$email]['count']++;
        $attempts[$email]['timestamp'] = time();
    }

    file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT));
}

// Helper function to start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Helper function to verify CSRF token
function verifyCsrfToken($token) {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * REGISTER - Step 1: Send verification code
 */
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
    if (getUserByEmail($email) !== null) {
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

/**
 * VERIFY - Step 2: Verify code and complete registration
 */
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

    // Create user account
    $users = getUsers();

    $userHash = md5($email);
    $newUser = [
        'email' => $email,
        'password_hash' => $pendingData['password_hash'],
        'user_hash' => $userHash,
        'email_verified' => true,
        'created_at' => time()
    ];

    $users[] = $newUser;
    saveUsers($users);

    // Create user data file
    $userDataFile = __DIR__ . '/../data/user_' . $userHash . '.json';
    $initialData = [
        'habits' => [],
        'habitData' => (object)[],
        'lastModified' => time()
    ];
    file_put_contents($userDataFile, json_encode($initialData, JSON_PRETTY_PRINT));

    // Remove pending registration
    removePendingRegistration($email);

    // Create session
    startSecureSession();
    $_SESSION['user_hash'] = $userHash;
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
    exit;
}

/**
 * RESEND - Resend verification code
 */
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

/**
 * LOGIN
 */
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

/**
 * LOGOUT
 */
if ($action === 'logout') {
    session_start();
    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}

/**
 * CHECK SESSION
 */
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

/**
 * GET CSRF TOKEN
 */
if ($action === 'csrf') {
    $token = generateCsrfToken();
    echo json_encode(['csrf_token' => $token]);
    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
