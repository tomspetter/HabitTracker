<?php
/**
 * Server Diagnostic Script
 *
 * This script checks if all required files exist and are readable.
 * Upload this to app/test_server.php and visit it in your browser.
 */

header('Content-Type: text/plain');

echo "=== HabitDot Server Diagnostic ===\n\n";

// Check PHP version
echo "PHP Version: " . phpversion() . "\n\n";

// Files to check
$requiredFiles = [
    'config.php',
    'email_config.php',
    'email_service.php',
    'verification_helpers.php',
    'api/auth.php',
    'api/data.php',
    'data/users.json',
    'data/verification_codes.json',
    'data/pending_registrations.json',
    'data/login_attempts.json'
];

echo "=== File Check ===\n";
foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $exists = file_exists($fullPath);
    $readable = $exists ? is_readable($fullPath) : false;

    $status = $exists ? ($readable ? '✓ EXISTS & READABLE' : '⚠ EXISTS BUT NOT READABLE') : '✗ MISSING';
    echo "$status - $file\n";

    if ($exists && !$readable) {
        echo "  Permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
    }
}

echo "\n=== Directory Permissions ===\n";
$dataDir = __DIR__ . '/data';
if (file_exists($dataDir)) {
    echo "✓ data/ exists\n";
    echo "  Writable: " . (is_writable($dataDir) ? 'YES' : 'NO') . "\n";
    echo "  Permissions: " . substr(sprintf('%o', fileperms($dataDir)), -4) . "\n";
} else {
    echo "✗ data/ directory missing\n";
}

echo "\n=== Testing require_once ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "✓ config.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ config.php failed: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/email_service.php';
    echo "✓ email_service.php loaded successfully\n";
    echo "  EMAIL_ENABLED: " . (defined('EMAIL_ENABLED') ? (EMAIL_ENABLED ? 'true' : 'false') : 'not defined') . "\n";
} catch (Exception $e) {
    echo "✗ email_service.php failed: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/verification_helpers.php';
    echo "✓ verification_helpers.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ verification_helpers.php failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "If you see any ✗ or ⚠ above, those files need to be uploaded or fixed.\n";
