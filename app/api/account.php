<?php
/**
 * Account API (MySQL Version)
 *
 * Handles account management operations:
 * - Change password
 * - Delete account (with all associated data)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$email = $_SESSION['email'];

// ============================================================================
// CHANGE PASSWORD
// ============================================================================

if ($action === 'change_password') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
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

    try {
        $pdo = getDBConnection();

        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE id = :id
        ");

        $stmt->execute([
            ':password_hash' => $newPasswordHash,
            ':id' => $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

    } catch (Exception $e) {
        error_log('Password change error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to change password']);
    }

    exit;
}

// ============================================================================
// DELETE ACCOUNT
// ============================================================================

if ($action === 'delete_account') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Validate password
    if (empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Password is required']);
        exit;
    }

    try {
        $pdo = getDBConnection();

        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
            exit;
        }

        // Delete user account
        // Note: Habits and habit_entries will be deleted automatically due to CASCADE
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        // Also clean up any pending registrations or verification codes for this email
        $pdo->prepare("DELETE FROM pending_registrations WHERE email = :email")
            ->execute([':email' => $email]);

        $pdo->prepare("DELETE FROM verification_codes WHERE email = :email")
            ->execute([':email' => $email]);

        $pdo->prepare("DELETE FROM login_attempts WHERE email = :email")
            ->execute([':email' => $email]);

        // Destroy session
        session_unset();
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);

    } catch (Exception $e) {
        error_log('Account deletion error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete account']);
    }

    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
