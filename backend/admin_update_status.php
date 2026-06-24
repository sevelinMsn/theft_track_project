<?php
/**
 * admin_update_status.php — Change report status and optionally add a note
 *
 * Method: POST (JSON)
 * Input: tracking_id, status, note (optional)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = getJsonInput();
$trackingId = strtoupper(cleanInput($input['tracking_id'] ?? '', 20));
$newStatus = cleanInput($input['status'] ?? '', 50);
$investigationNote = cleanInput($input['note'] ?? '', 2000);

if ($trackingId === '' || $newStatus === '') {
    jsonResponse(false, 'Tracking ID and status are required.');
}

if (!in_array($newStatus, ALLOWED_STATUSES, true)) {
    jsonResponse(false, 'Invalid status. Use: Pending, Under Investigation, or Resolved.');
}

$reportRow = getReportByTrackingId($conn, $trackingId);
if (!$reportRow) {
    jsonResponse(false, 'Report not found.');
}

$oldStatus = $reportRow['status'];
$updateStatement = $conn->prepare('UPDATE reports SET status = ? WHERE tracking_id = ?');
$updateStatement->bind_param('ss', $newStatus, $trackingId);
$updateStatement->execute();

$reportDatabaseId = (int) $reportRow['id'];
$adminName = $_SESSION['admin_name'] ?? 'Admin';

if ($oldStatus !== $newStatus) {
    logCaseUpdate($conn, $reportDatabaseId, 'status', $newStatus, null, $adminName);
}

if ($investigationNote !== '') {
    logCaseUpdate($conn, $reportDatabaseId, 'note', null, $investigationNote, $adminName);
}

$updatedRow = getReportByTrackingId($conn, $trackingId);

jsonResponse(true, 'Case updated successfully.', [
    'report' => formatReportForApi($conn, $updatedRow, true),
]);
