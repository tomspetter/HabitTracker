<?php
/**
 * Authentication API
 *
 * Handles user registration, login, logout, and session management.
 */

require_once __DIR__ . '/../config.php';

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

// Helper function to check login attempts
function checkLoginAttempts($username) {
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
        return true;
    }

    $attempts = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: [];

    if (!isset($attempts[$username])) {
        return true;
    }

    $userAttempts = $attempts[$username];

    // Clean old attempts
    if (time() - $userAttempts['timestamp'] > LOGIN_LOCKOUT_TIME) {
        unset($attempts[$username]);
        file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT));
        return true;
    }

    return $userAttempts['count'] < MAX_LOGIN_ATTEMPTS;
}

// Helper function to record failed login
function recordFailedLogin($username) {
    $attempts = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: [];

    if (!isset($attempts[$username])) {
        $attempts[$username] = ['count' => 1, 'timestamp' => time()];
    } else {
        $attempts[$username]['count']++;
        $attempts[$username]['timestamp'] = time();
    }

    file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT));
}

// Helper function to clear login attempts
function clearLoginAttempts($username) {
    $attempts = json_decode(file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: [];

    if (isset($attempts[$username])) {
        unset($attempts[$username]);
        file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT));
    }
}

switch ($action) {
    case 'register':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        // Validation
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            exit;
        }

        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
            exit;
        }

        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            exit;
        }

        // Check if username exists
        $users = getUsers();

        if (isset($users[$username])) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }

        // Create user
        $userId = bin2hex(random_bytes(16));
        $users[$username] = [
            'id' => $userId,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created' => time()
        ];

        saveUsers($users);

        // Create empty data file for user
        $userDataFile = DATA_DIR . "/user_{$userId}.json";
        file_put_contents($userDataFile, json_encode([
            'habits' => [],
            'habitData' => []
        ], JSON_PRETTY_PRINT));

        echo json_encode(['success' => true, 'message' => 'Account created successfully']);
        break;

    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        // Check rate limiting
        if (!checkLoginAttempts($username)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please try again in 15 minutes.']);
            exit;
        }

        // Validation
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            exit;
        }

        // Check credentials
        $users = getUsers();

        if (!isset($users[$username])) {
            recordFailedLogin($username);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            exit;
        }

        $user = $users[$username];

        if (!password_verify($password, $user['password'])) {
            recordFailedLogin($username);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            exit;
        }

        // Clear failed attempts
        clearLoginAttempts($username);

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();

        // Generate CSRF token
        $csrfToken = generateCSRFToken();

        echo json_encode([
            'success' => true,
            'username' => $username,
            'csrf_token' => $csrfToken
        ]);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'check':
        if (isLoggedIn()) {
            // Check session timeout
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
                session_destroy();
                echo json_encode(['success' => false, 'loggedIn' => false, 'error' => 'Session expired']);
            } else {
                echo json_encode([
                    'success' => true,
                    'loggedIn' => true,
                    'username' => $_SESSION['username'],
                    'csrf_token' => generateCSRFToken()
                ]);
            }
        } else {
            echo json_encode(['success' => true, 'loggedIn' => false]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
