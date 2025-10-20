<?php
/**
 * Data API (MySQL Version with Encryption)
 *
 * Handles saving and loading habit data for authenticated users.
 * Uses MySQL database with server-side encryption for habit names.
 *
 * ENCRYPTION: Habit names are encrypted before storing in database and
 * decrypted when loading. This is transparent to the frontend.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Require authentication
requireLogin();

$userId = $_SESSION['user_id']; // User ID from MySQL database
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// ============================================================================
// LOAD HABIT DATA
// ============================================================================

if ($action === 'load') {
    try {
        $pdo = getDBConnection();

        // Load habits for this user
        $stmt = $pdo->prepare("
            SELECT id, name_encrypted, color, sort_order
            FROM habits
            WHERE user_id = :user_id
            ORDER BY sort_order ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        $habits = $stmt->fetchAll();

        // Decrypt habit names
        $decryptedHabits = [];
        foreach ($habits as $habit) {
            $decryptedName = decryptHabitName($habit['name_encrypted'], $userId);

            if ($decryptedName === false) {
                // Decryption failed - log error and skip this habit
                error_log("Failed to decrypt habit ID {$habit['id']} for user $userId");
                continue;
            }

            $decryptedHabits[] = [
                'id' => (string)$habit['id'],
                'name' => $decryptedName,
                'color' => $habit['color']
            ];
        }

        // Load habit entries
        // Format: { habitId: { "2025-01-15": true, "2025-01-16": true } }
        $habitData = [];

        if (!empty($decryptedHabits)) {
            $habitIds = array_column($decryptedHabits, 'id');
            $placeholders = implode(',', array_fill(0, count($habitIds), '?'));

            $stmt = $pdo->prepare("
                SELECT habit_id, date, completed
                FROM habit_entries
                WHERE habit_id IN ($placeholders)
            ");
            $stmt->execute($habitIds);
            $entries = $stmt->fetchAll();

            foreach ($entries as $entry) {
                $habitId = (string)$entry['habit_id'];
                $date = $entry['date'];
                $completed = (bool)$entry['completed'];

                if (!isset($habitData[$habitId])) {
                    $habitData[$habitId] = [];
                }

                $habitData[$habitId][$date] = $completed;
            }
        }

        // Return data in the format expected by frontend
        echo json_encode([
            'success' => true,
            'data' => [
                'habits' => $decryptedHabits,
                'habitData' => (object)$habitData // Empty object if no data
            ]
        ]);

    } catch (Exception $e) {
        error_log('Error loading habit data: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to load data']);
    }

    exit;
}

// ============================================================================
// SAVE HABIT DATA
// ============================================================================

if ($action === 'save') {
    // Validate CSRF token
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Get data to save
    $habits = $input['habits'] ?? [];
    $habitData = $input['habitData'] ?? [];

    // Basic validation
    if (!is_array($habits) || !is_array($habitData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data format']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Get existing habit IDs for this user
        $stmt = $pdo->prepare("SELECT id FROM habits WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $existingHabitIds = array_column($stmt->fetchAll(), 'id');

        // Process habits
        $processedHabitIds = [];
        $habitIdMap = []; // Map frontend IDs to database IDs

        $insertStmt = $pdo->prepare("
            INSERT INTO habits (user_id, name_encrypted, color, sort_order)
            VALUES (:user_id, :name_encrypted, :color, :sort_order)
        ");

        $updateStmt = $pdo->prepare("
            UPDATE habits
            SET name_encrypted = :name_encrypted, color = :color, sort_order = :sort_order
            WHERE id = :id AND user_id = :user_id
        ");

        foreach ($habits as $index => $habit) {
            $habitId = $habit['id'] ?? null;
            $habitName = $habit['name'] ?? '';
            $color = $habit['color'] ?? 'blue';

            if (empty($habitName)) {
                continue; // Skip empty habit names
            }

            // ENCRYPT THE HABIT NAME
            try {
                $encryptedName = encryptHabitName($habitName, $userId);
            } catch (Exception $e) {
                error_log("Encryption failed for user $userId: " . $e->getMessage());
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Encryption error']);
                exit;
            }

            // Check if this is an existing habit or new one
            if ($habitId && in_array($habitId, $existingHabitIds)) {
                // Update existing habit
                $updateStmt->execute([
                    ':name_encrypted' => $encryptedName,
                    ':color' => $color,
                    ':sort_order' => $index,
                    ':id' => $habitId,
                    ':user_id' => $userId
                ]);

                $processedHabitIds[] = $habitId;
                $habitIdMap[$habitId] = $habitId; // Same ID

            } else {
                // Insert new habit
                $insertStmt->execute([
                    ':user_id' => $userId,
                    ':name_encrypted' => $encryptedName,
                    ':color' => $color,
                    ':sort_order' => $index
                ]);

                $newId = $pdo->lastInsertId();
                $processedHabitIds[] = $newId;

                // Map old frontend ID to new database ID
                if ($habitId) {
                    $habitIdMap[$habitId] = $newId;
                }
            }
        }

        // Delete habits that were removed
        $habitsToDelete = array_diff($existingHabitIds, $processedHabitIds);
        if (!empty($habitsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($habitsToDelete), '?'));
            $stmt = $pdo->prepare("
                DELETE FROM habits
                WHERE user_id = ? AND id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$userId], $habitsToDelete));
        }

        // Process habit entries
        // First, delete all existing entries for these habits to simplify logic
        if (!empty($processedHabitIds)) {
            $placeholders = implode(',', array_fill(0, count($processedHabitIds), '?'));
            $stmt = $pdo->prepare("
                DELETE FROM habit_entries
                WHERE habit_id IN ($placeholders)
            ");
            $stmt->execute($processedHabitIds);
        }

        // Insert habit entries
        $entryStmt = $pdo->prepare("
            INSERT INTO habit_entries (habit_id, date, completed)
            VALUES (:habit_id, :date, :completed)
        ");

        foreach ($habitData as $frontendHabitId => $dates) {
            // Get the database habit ID
            $dbHabitId = $habitIdMap[$frontendHabitId] ?? $frontendHabitId;

            // Only process if this habit ID exists in our processed list
            if (!in_array($dbHabitId, $processedHabitIds)) {
                continue;
            }

            foreach ($dates as $date => $completed) {
                // Only save completed entries (true values)
                if ($completed) {
                    $entryStmt->execute([
                        ':habit_id' => $dbHabitId,
                        ':date' => $date,
                        ':completed' => true
                    ]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Data saved successfully'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Error saving habit data: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save data']);
    }

    exit;
}

// ============================================================================
// EXPORT HABIT DATA
// ============================================================================

if ($action === 'export') {
    try {
        $pdo = getDBConnection();
        $format = $_GET['format'] ?? 'json';

        // Load habits
        $stmt = $pdo->prepare("
            SELECT id, name_encrypted, color
            FROM habits
            WHERE user_id = :user_id
            ORDER BY sort_order ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        $habits = $stmt->fetchAll();

        // Decrypt habit names
        $decryptedHabits = [];
        foreach ($habits as $habit) {
            $decryptedName = decryptHabitName($habit['name_encrypted'], $userId);

            if ($decryptedName === false) {
                error_log("Failed to decrypt habit ID {$habit['id']} for user $userId during export");
                continue;
            }

            $decryptedHabits[] = [
                'id' => (string)$habit['id'],
                'name' => $decryptedName,
                'color' => $habit['color']
            ];
        }

        // Load habit entries
        $habitData = [];
        if (!empty($decryptedHabits)) {
            $habitIds = array_column($habits, 'id');
            $placeholders = implode(',', array_fill(0, count($habitIds), '?'));

            $stmt = $pdo->prepare("
                SELECT habit_id, date, completed
                FROM habit_entries
                WHERE habit_id IN ($placeholders)
                ORDER BY date ASC
            ");
            $stmt->execute($habitIds);
            $entries = $stmt->fetchAll();

            foreach ($entries as $entry) {
                $habitId = (string)$entry['habit_id'];
                $date = $entry['date'];
                $completed = (bool)$entry['completed'];

                if (!isset($habitData[$habitId])) {
                    $habitData[$habitId] = [];
                }

                $habitData[$habitId][$date] = $completed;
            }
        }

        if ($format === 'csv') {
            // Export as CSV
            $csvData = [];
            $csvData[] = ['Habit ID', 'Habit Name', 'Date', 'Completed'];

            foreach ($decryptedHabits as $habit) {
                $habitId = $habit['id'];
                $habitName = $habit['name'];
                $dates = $habitData[$habitId] ?? [];

                foreach ($dates as $date => $completed) {
                    if ($completed) {
                        $csvData[] = [$habitId, $habitName, $date, 'true'];
                    }
                }
            }

            // Convert to CSV string
            $output = fopen('php://temp', 'r+');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            // Set headers for CSV download
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="habit-tracker-backup-' . date('Y-m-d') . '.csv"');
            header('Content-Length: ' . strlen($csvContent));
            header('Pragma: public');

            echo $csvContent;

        } else {
            // Export as JSON (default)
            $data = [
                'habits' => $decryptedHabits,
                'habitData' => $habitData,
                'exportDate' => date('Y-m-d H:i:s')
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT);

            header('Content-Description: File Transfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="habit-tracker-backup-' . date('Y-m-d') . '.json"');
            header('Content-Length: ' . strlen($jsonContent));
            header('Pragma: public');

            echo $jsonContent;
        }

    } catch (Exception $e) {
        error_log('Error exporting data: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to export data']);
    }

    exit;
}

// ============================================================================
// IMPORT HABIT DATA
// ============================================================================

if ($action === 'import') {
    // Validate CSRF token
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Get imported data
    $importedData = $input['data'] ?? null;

    if (!is_array($importedData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data format']);
        exit;
    }

    // Validate structure
    if (!isset($importedData['habits']) || !isset($importedData['habitData'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data structure']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Delete all existing data for this user
        $pdo->prepare("DELETE FROM habits WHERE user_id = :user_id")
            ->execute([':user_id' => $userId]);

        // Insert imported habits with encryption
        $habitIdMap = [];
        $insertStmt = $pdo->prepare("
            INSERT INTO habits (user_id, name_encrypted, color, sort_order)
            VALUES (:user_id, :name_encrypted, :color, :sort_order)
        ");

        foreach ($importedData['habits'] as $index => $habit) {
            $oldId = $habit['id'];
            $habitName = $habit['name'];
            $color = $habit['color'] ?? 'blue';

            // Encrypt habit name
            $encryptedName = encryptHabitName($habitName, $userId);

            $insertStmt->execute([
                ':user_id' => $userId,
                ':name_encrypted' => $encryptedName,
                ':color' => $color,
                ':sort_order' => $index
            ]);

            $newId = $pdo->lastInsertId();
            $habitIdMap[$oldId] = $newId;
        }

        // Insert habit entries
        $entryStmt = $pdo->prepare("
            INSERT INTO habit_entries (habit_id, date, completed)
            VALUES (:habit_id, :date, :completed)
            ON DUPLICATE KEY UPDATE completed = :completed
        ");

        foreach ($importedData['habitData'] as $oldHabitId => $dates) {
            $newHabitId = $habitIdMap[$oldHabitId] ?? null;

            if (!$newHabitId) {
                continue; // Skip if habit wasn't imported
            }

            foreach ($dates as $date => $completed) {
                if ($completed) {
                    $entryStmt->execute([
                        ':habit_id' => $newHabitId,
                        ':date' => $date,
                        ':completed' => true
                    ]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Data imported successfully'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Error importing data: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to import data']);
    }

    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
