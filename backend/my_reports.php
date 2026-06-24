<?php
/**
 * my_reports.php — List all reports belonging to the logged-in user
 *
 * Method: GET
 * Output: { reports: [ … ] }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$loggedInUser = requireUserSession();

$statement = $conn->prepare(
    'SELECT * FROM reports
     WHERE user_id = ? OR (user_id IS NULL AND email = ?)
     ORDER BY created_at DESC'
);
$statement->bind_param('is', $loggedInUser['id'], $loggedInUser['email']);
$statement->execute();
$result = $statement->get_result();

$reportList = [];
while ($reportRow = $result->fetch_assoc()) {
    $reportList[] = formatReportForApi($conn, $reportRow);
}

jsonResponse(true, 'Reports loaded.', ['reports' => $reportList]);
