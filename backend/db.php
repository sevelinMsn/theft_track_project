<?php
/**
 * db.php — Connect to MySQL
 *
 * After this file runs, other scripts can use the variable: $conn
 * Example: $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
 */
require_once __DIR__ . '/config.php';

// Show database errors as exceptions (easier to debug during development)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    // Connection failed — send a clear JSON error to the frontend
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Import sql/schema.sql in phpMyAdmin and check config.php.',
    ]);
    exit;
}
