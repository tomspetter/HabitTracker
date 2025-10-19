<?php
require_once '../config.php';

// Start session and check authentication
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$username = $_SESSION['username'];

// Change Password
if ($action === 'change_password') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Validate passwords
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
        exit;
    }

    // Load users file
    $usersFile = DATA_DIR . '/users.json';
    if (!file_exists($usersFile)) {
        echo json_encode(['success' => false, 'error' => 'User data not found']);
        exit;
    }

    $users = json_decode(file_get_contents($usersFile), true);

    if (!isset($users[$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Verify current password
    if (!password_verify($currentPassword, $users[$username]['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }

    // Update password
    $users[$username]['password'] = password_hash($newPassword, PASSWORD_BCRYPT);

    // Save users file
    if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to save password']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// Delete Account
if ($action === 'delete_account') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Validate password
    if (empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Password is required']);
        exit;
    }

    // Load users file
    $usersFile = DATA_DIR . '/users.json';
    if (!file_exists($usersFile)) {
        echo json_encode(['success' => false, 'error' => 'User data not found']);
        exit;
    }

    $users = json_decode(file_get_contents($usersFile), true);

    if (!isset($users[$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $users[$username]['password'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }

    // Delete user data file
    $userDataFile = DATA_DIR . '/user_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $username) . '.json';
    if (file_exists($userDataFile)) {
        unlink($userDataFile);
    }

    // Remove user from users file
    unset($users[$username]);
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

    // Destroy session
    session_unset();
    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
