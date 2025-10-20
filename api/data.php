<?php
/**
 * Data API
 *
 * Handles saving and loading habit data for authenticated users.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Require authentication
requireLogin();

$userId = $_SESSION['user_id'];
$userDataFile = DATA_DIR . "/user_{$userId}.json";

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'load':
        // Load user's habit data
        if (!file_exists($userDataFile)) {
            // Create empty data file if it doesn't exist
            $emptyData = [
                'habits' => [],
                'habitData' => []
            ];
            file_put_contents($userDataFile, json_encode($emptyData, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'data' => $emptyData]);
        } else {
            $data = json_decode(file_get_contents($userDataFile), true);
            echo json_encode(['success' => true, 'data' => $data]);
        }
        break;

    case 'save':
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

        // Save data
        $data = [
            'habits' => $habits,
            'habitData' => $habitData,
            'lastModified' => time()
        ];

        if (file_put_contents($userDataFile, json_encode($data, JSON_PRETTY_PRINT)) !== false) {
            echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save data']);
        }
        break;

    case 'export':
        // Export user's data
        if (!file_exists($userDataFile)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No data found']);
            exit;
        }

        $data = json_decode(file_get_contents($userDataFile), true);
        $format = $_GET['format'] ?? 'json';

        if ($format === 'csv') {
            // Export as CSV
            $csvData = [];
            $csvData[] = ['Habit ID', 'Habit Name', 'Date', 'Completed'];

            $habits = $data['habits'] ?? [];
            $habitData = $data['habitData'] ?? [];

            foreach ($habits as $habit) {
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
            $jsonContent = json_encode($data, JSON_PRETTY_PRINT);

            header('Content-Description: File Transfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="habit-tracker-backup-' . date('Y-m-d') . '.json"');
            header('Content-Length: ' . strlen($jsonContent));
            header('Pragma: public');

            echo $jsonContent;
        }
        break;

    case 'import':
        // Import user's data from JSON
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

        // Save imported data
        $data = [
            'habits' => $importedData['habits'],
            'habitData' => $importedData['habitData'],
            'lastModified' => time()
        ];

        if (file_put_contents($userDataFile, json_encode($data, JSON_PRETTY_PRINT)) !== false) {
            echo json_encode(['success' => true, 'message' => 'Data imported successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to import data']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
