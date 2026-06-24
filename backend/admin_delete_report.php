<?php
/**
 * admin_delete_report.php — Permanently delete a report
 *
 * Method: POST (JSON)
 * Input: tracking_id
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = getJsonInput();
$trackingId = strtoupper(cleanInput($input['tracking_id'] ?? '', 20));

if ($trackingId === '') {
    jsonResponse(false, 'Tracking ID is required.');
}

$deleteStatement = $conn->prepare('DELETE FROM reports WHERE tracking_id = ?');
$deleteStatement->bind_param('s', $trackingId);
$deleteStatement->execute();

if ($deleteStatement->affected_rows === 0) {
    jsonResponse(false, 'Report not found.');
}

jsonResponse(true, 'Report deleted successfully.');
