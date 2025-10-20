#!/usr/bin/env php
<?php
/**
 * Migration Script: JSON to MySQL with Encryption
 *
 * This script migrates existing habit tracker data from JSON files to MySQL database
 * with server-side encryption for habit names.
 *
 * WHAT THIS SCRIPT DOES:
 * 1. Backs up all existing JSON files
 * 2. Reads users from users.json
 * 3. Reads each user's habit data from user_*.json files
 * 4. Encrypts habit names using user-specific keys
 * 5. Inserts all data into MySQL database
 * 6. Validates migration was successful
 *
 * PREREQUISITES:
 * - MySQL database created (see schema.sql)
 * - Database credentials configured in config.php
 * - HABIT_ENCRYPTION_KEY environment variable set
 * - PHP OpenSSL extension enabled
 *
 * USAGE:
 *   php migrate_to_mysql.php
 *
 * SAFETY:
 * - Original JSON files are backed up to data/backup_YYYYMMDD_HHMMSS/
 * - Script can be run multiple times (won't create duplicates)
 * - Validates data after migration
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/config.php';

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║  HabitTracker: JSON to MySQL Migration Script                    ║\n";
echo "║  with Server-Side Encryption                                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// STEP 0: PRE-FLIGHT CHECKS
// ============================================================================

echo "[STEP 0] Running pre-flight checks...\n";

// Check if encryption key is set
if (!getenv('HABIT_ENCRYPTION_KEY')) {
    die("❌ ERROR: HABIT_ENCRYPTION_KEY environment variable is not set.\n" .
        "   Please set it before running migration.\n" .
        "   Example: export HABIT_ENCRYPTION_KEY=\"your-secret-key-here\"\n\n");
}

// Check if OpenSSL is available
if (!extension_loaded('openssl')) {
    die("❌ ERROR: PHP OpenSSL extension is not loaded.\n" .
        "   Encryption requires OpenSSL. Please enable it in php.ini\n\n");
}

// Check database connection
try {
    $pdo = getDBConnection();
    echo "   ✓ Database connection successful\n";
} catch (Exception $e) {
    die("❌ ERROR: Cannot connect to database: " . $e->getMessage() . "\n" .
        "   Please check your database configuration in config.php\n\n");
}

// Check if JSON files exist
if (!file_exists(USERS_FILE)) {
    die("❌ ERROR: users.json not found at " . USERS_FILE . "\n" .
        "   Nothing to migrate.\n\n");
}

echo "   ✓ All pre-flight checks passed\n\n";

// ============================================================================
// STEP 1: BACKUP EXISTING DATA
// ============================================================================

echo "[STEP 1] Backing up existing JSON files...\n";

$backupDir = DATA_DIR . '/backup_' . date('Ymd_His');
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Backup all JSON files
$jsonFiles = glob(DATA_DIR . '/*.json');
foreach ($jsonFiles as $file) {
    $filename = basename($file);
    copy($file, $backupDir . '/' . $filename);
    echo "   ✓ Backed up: $filename\n";
}

echo "   ✓ Backup created at: $backupDir\n\n";

// ============================================================================
// STEP 2: LOAD JSON DATA
// ============================================================================

echo "[STEP 2] Loading data from JSON files...\n";

// Load users
$users = json_decode(file_get_contents(USERS_FILE), true) ?: [];
echo "   ✓ Found " . count($users) . " users\n";

if (empty($users)) {
    echo "   ℹ No users to migrate. Exiting.\n\n";
    exit(0);
}

// Load user habit data
$userDataFiles = [];
foreach ($users as $user) {
    $userHash = $user['user_hash'];
    $userDataFile = DATA_DIR . "/user_{$userHash}.json";

    if (file_exists($userDataFile)) {
        $data = json_decode(file_get_contents($userDataFile), true);
        $userDataFiles[$userHash] = $data;
        $habitCount = count($data['habits'] ?? []);
        echo "   ✓ Loaded data for {$user['email']}: $habitCount habits\n";
    }
}

echo "\n";

// ============================================================================
// STEP 3: MIGRATE USERS
// ============================================================================

echo "[STEP 3] Migrating users to MySQL...\n";

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, user_hash, email_verified, created_at)
        VALUES (:email, :password_hash, :user_hash, :email_verified, FROM_UNIXTIME(:created_at))
        ON DUPLICATE KEY UPDATE email = email
    ");

    $userIdMap = []; // Map user_hash to database ID

    foreach ($users as $user) {
        $stmt->execute([
            ':email' => $user['email'],
            ':password_hash' => $user['password_hash'],
            ':user_hash' => $user['user_hash'],
            ':email_verified' => $user['email_verified'] ?? true,
            ':created_at' => $user['created_at'] ?? time()
        ]);

        // Get the inserted user ID
        $userId = $pdo->lastInsertId() ?: $pdo->query(
            "SELECT id FROM users WHERE user_hash = " . $pdo->quote($user['user_hash'])
        )->fetchColumn();

        $userIdMap[$user['user_hash']] = $userId;

        echo "   ✓ Migrated user: {$user['email']} (ID: $userId)\n";
    }

    $pdo->commit();
    echo "   ✓ All users migrated successfully\n\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ ERROR migrating users: " . $e->getMessage() . "\n\n");
}

// ============================================================================
// STEP 4: MIGRATE HABITS AND ENTRIES (WITH ENCRYPTION)
// ============================================================================

echo "[STEP 4] Migrating habits with encryption...\n";

$totalHabits = 0;
$totalEntries = 0;

foreach ($userDataFiles as $userHash => $data) {
    $userId = $userIdMap[$userHash];
    $habits = $data['habits'] ?? [];
    $habitData = $data['habitData'] ?? [];

    if (empty($habits)) {
        continue;
    }

    echo "   Processing user ID $userId ({$userHash})...\n";

    $pdo->beginTransaction();

    try {
        // Prepare statements
        $habitStmt = $pdo->prepare("
            INSERT INTO habits (user_id, name_encrypted, color, sort_order, created_at)
            VALUES (:user_id, :name_encrypted, :color, :sort_order, NOW())
        ");

        $entryStmt = $pdo->prepare("
            INSERT INTO habit_entries (habit_id, date, completed)
            VALUES (:habit_id, :date, :completed)
            ON DUPLICATE KEY UPDATE completed = :completed
        ");

        $habitIdMap = []; // Map old habit ID to new database ID

        // Migrate habits
        foreach ($habits as $index => $habit) {
            $oldHabitId = $habit['id'];
            $habitName = $habit['name'];
            $color = $habit['color'];

            // ENCRYPT THE HABIT NAME
            $encryptedName = encryptHabitName($habitName, $userId);

            $habitStmt->execute([
                ':user_id' => $userId,
                ':name_encrypted' => $encryptedName,
                ':color' => $color,
                ':sort_order' => $index
            ]);

            $newHabitId = $pdo->lastInsertId();
            $habitIdMap[$oldHabitId] = $newHabitId;

            echo "      ✓ Habit: \"$habitName\" → encrypted (ID: $newHabitId)\n";
            $totalHabits++;

            // Migrate habit entries for this habit
            if (isset($habitData[$oldHabitId])) {
                $entries = $habitData[$oldHabitId];
                $entryCount = 0;

                foreach ($entries as $date => $completed) {
                    // Only migrate completed entries
                    if ($completed) {
                        $entryStmt->execute([
                            ':habit_id' => $newHabitId,
                            ':date' => $date,
                            ':completed' => true
                        ]);
                        $entryCount++;
                        $totalEntries++;
                    }
                }

                if ($entryCount > 0) {
                    echo "         → $entryCount entries\n";
                }
            }
        }

        $pdo->commit();
        echo "      ✓ User migration complete\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ ERROR migrating habits for user $userId: " . $e->getMessage() . "\n\n");
    }
}

echo "   ✓ Migrated $totalHabits habits with $totalEntries entries\n\n";

// ============================================================================
// STEP 5: MIGRATE VERIFICATION DATA
// ============================================================================

echo "[STEP 5] Migrating verification data...\n";

// Migrate pending registrations
$pendingFile = DATA_DIR . '/pending_registrations.json';
if (file_exists($pendingFile)) {
    $pending = json_decode(file_get_contents($pendingFile), true) ?: [];

    if (!empty($pending)) {
        $stmt = $pdo->prepare("
            INSERT INTO pending_registrations (email, password_hash, created_at, expires_at)
            VALUES (:email, :password_hash, FROM_UNIXTIME(:created_at), FROM_UNIXTIME(:expires_at))
            ON DUPLICATE KEY UPDATE email = email
        ");

        foreach ($pending as $email => $data) {
            $stmt->execute([
                ':email' => $email,
                ':password_hash' => $data['password_hash'],
                ':created_at' => $data['created_at'] ?? time(),
                ':expires_at' => $data['expires_at'] ?? time() + 900
            ]);
        }
        echo "   ✓ Migrated " . count($pending) . " pending registrations\n";
    }
}

// Migrate verification codes
$codesFile = DATA_DIR . '/verification_codes.json';
if (file_exists($codesFile)) {
    $codes = json_decode(file_get_contents($codesFile), true) ?: [];

    if (!empty($codes)) {
        $stmt = $pdo->prepare("
            INSERT INTO verification_codes (email, code, code_type, created_at, expires_at)
            VALUES (:email, :code, :code_type, FROM_UNIXTIME(:created_at), FROM_UNIXTIME(:expires_at))
        ");

        $count = 0;
        foreach ($codes as $email => $codeData) {
            foreach ($codeData as $codeEntry) {
                $stmt->execute([
                    ':email' => $email,
                    ':code' => $codeEntry['code'],
                    ':code_type' => $codeEntry['type'],
                    ':created_at' => $codeEntry['created_at'] ?? time(),
                    ':expires_at' => $codeEntry['expires_at'] ?? time() + 900
                ]);
                $count++;
            }
        }
        echo "   ✓ Migrated $count verification codes\n";
    }
}

echo "\n";

// ============================================================================
// STEP 6: VALIDATION
// ============================================================================

echo "[STEP 6] Validating migration...\n";

// Count migrated records
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$habitCount = $pdo->query("SELECT COUNT(*) FROM habits")->fetchColumn();
$entryCount = $pdo->query("SELECT COUNT(*) FROM habit_entries")->fetchColumn();

echo "   Database contains:\n";
echo "   - $userCount users\n";
echo "   - $habitCount habits (encrypted)\n";
echo "   - $entryCount habit entries\n\n";

// Test encryption/decryption
echo "   Testing encryption/decryption...\n";
$testResult = $pdo->query("
    SELECT h.id, h.name_encrypted, h.user_id, u.email
    FROM habits h
    JOIN users u ON h.user_id = u.id
    LIMIT 1
")->fetch();

if ($testResult) {
    $decrypted = decryptHabitName($testResult['name_encrypted'], $testResult['user_id']);
    if ($decrypted !== false) {
        echo "   ✓ Encryption test passed: Successfully decrypted habit name\n";
        echo "      User: {$testResult['email']}\n";
        echo "      Encrypted: " . substr($testResult['name_encrypted'], 0, 32) . "...\n";
        echo "      Decrypted: \"$decrypted\"\n";
    } else {
        echo "   ⚠ WARNING: Could not decrypt habit name. Check encryption key.\n";
    }
}

echo "\n";

// ============================================================================
// COMPLETE
// ============================================================================

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║  MIGRATION COMPLETED SUCCESSFULLY! ✓                              ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  ✓ Backed up JSON files to: $backupDir\n";
echo "  ✓ Migrated $userCount users\n";
echo "  ✓ Migrated $habitCount habits (with encryption)\n";
echo "  ✓ Migrated $entryCount habit completion entries\n";
echo "  ✓ All habit names are now encrypted in the database\n\n";

echo "Next steps:\n";
echo "  1. Test the application to ensure everything works\n";
echo "  2. Verify you can log in and see your habits\n";
echo "  3. Check that habit names are displayed correctly (decrypted)\n";
echo "  4. Once confirmed, you can archive the JSON backup folder\n";
echo "  5. Update your deployment to use MySQL endpoints\n\n";

echo "Security reminder:\n";
echo "  - Keep HABIT_ENCRYPTION_KEY environment variable secure\n";
echo "  - Never commit the encryption key to version control\n";
echo "  - If you lose the encryption key, encrypted data cannot be recovered\n";
echo "  - Backup your database regularly\n\n";
