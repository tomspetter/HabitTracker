<?php
/**
 * Test Auth.php Script
 *
 * This script simulates the auth check action to see what error occurs.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== Testing Auth Check Action ===\n\n";

try {
    echo "1. Loading config.php...\n";
    require_once __DIR__ . '/config.php';
    echo "   ✓ Success\n\n";

    echo "2. Loading verification_helpers.php...\n";
    require_once __DIR__ . '/verification_helpers.php';
    echo "   ✓ Success\n\n";

    echo "3. Loading email_service.php...\n";
    require_once __DIR__ . '/email_service.php';
    echo "   ✓ Success\n\n";

    echo "4. Testing session functions...\n";

    // Check if startSecureSession exists
    if (function_exists('startSecureSession')) {
        echo "   ✓ startSecureSession() exists\n";
        startSecureSession();
        echo "   ✓ Session started\n";
    } else {
        echo "   ✗ startSecureSession() not found\n";
        echo "   Attempting regular session_start()...\n";
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            echo "   ✓ Session started with session_start()\n";
        }
    }

    echo "\n5. Checking session variables...\n";
    echo "   user_hash: " . (isset($_SESSION['user_hash']) ? 'SET' : 'NOT SET') . "\n";
    echo "   email: " . (isset($_SESSION['email']) ? 'SET' : 'NOT SET') . "\n";
    echo "   csrf_token: " . (isset($_SESSION['csrf_token']) ? 'SET' : 'NOT SET') . "\n";

    echo "\n6. Testing CSRF token generation...\n";
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo "   ✓ Generated new CSRF token\n";
    } else {
        echo "   ✓ CSRF token already exists\n";
    }

    echo "\n7. Testing JSON response...\n";
    $response = [
        'loggedIn' => false,
        'test' => 'This is a test response'
    ];
    echo "   Response data: " . json_encode($response) . "\n";

    echo "\n=== Test Complete - No Errors Found ===\n";
    echo "If auth.php still fails, there may be an issue in the auth.php file itself.\n";

} catch (Error $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\n✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}
