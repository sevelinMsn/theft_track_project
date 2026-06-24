<?php
/**
 * admin_add_note.php — Add an investigation note to a report
 *
 * Method: POST (JSON)
 * Input: tracking_id, note
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = getJsonInput();
$trackingId = strtoupper(cleanInput($input['tracking_id'] ?? '', 20));
$noteText = cleanInput($input['note'] ?? '', 2000);

if ($trackingId === '' || $noteText === '') {
    jsonResponse(false, 'Tracking ID and note are required.');
}

$reportRow = getReportByTrackingId($conn, $trackingId);
if (!$reportRow) {
    jsonResponse(false, 'Report not found.');
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
logCaseUpdate($conn, (int) $reportRow['id'], 'note', null, $noteText, $adminName);

$updatedRow = getReportByTrackingId($conn, $trackingId);

jsonResponse(true, 'Investigation note added.', [
    'report' => formatReportForApi($conn, $updatedRow, true),
]);
