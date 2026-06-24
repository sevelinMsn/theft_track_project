<?php
/**
 * report_detail.php — Full details for one report (logged-in owner only)
 *
 * Method: GET
 * Input: tracking_id
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$loggedInUser = requireUserSession();
$trackingId = strtoupper(cleanInput($_GET['tracking_id'] ?? '', 20));

if ($trackingId === '') {
    jsonResponse(false, 'Tracking ID is required.');
}

$reportRow = getReportByTrackingId($conn, $trackingId);

if (!$reportRow) {
    jsonResponse(false, 'Report not found.', [], 404);
}

if (!userOwnsReport($loggedInUser, $reportRow)) {
    jsonResponse(false, 'You do not have access to this report.', [], 403);
}

jsonResponse(true, 'Report loaded.', [
    'report' => formatReportForApi($conn, $reportRow),
]);
