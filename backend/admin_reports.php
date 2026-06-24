<?php
/**
 * admin_reports.php — List theft reports for the admin panel
 *
 * Method: GET
 * Input: q (optional search text)
 * Output: reports[], stats{}
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

$searchText = cleanInput($_GET['q'] ?? '', 100);

if ($searchText !== '') {
    $searchPattern = '%' . $searchText . '%';
    $statement = $conn->prepare(
        'SELECT r.* FROM reports r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.tracking_id LIKE ? OR r.fullname LIKE ? OR r.email LIKE ?
         OR r.phone LIKE ? OR u.email LIKE ? OR u.fullname LIKE ?
         ORDER BY r.created_at DESC'
    );
    $statement->bind_param('ssssss', $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    $statement->execute();
    $result = $statement->get_result();
} else {
    $result = $conn->query('SELECT * FROM reports ORDER BY created_at DESC');
}

$reportList = [];
while ($reportRow = $result->fetch_assoc()) {
    $reportList[] = formatReportForApi($conn, $reportRow, true);
}

// Count reports by status
$statusCounts = ['total' => 0, 'pending' => 0, 'investigating' => 0, 'resolved' => 0];
$statsResult = $conn->query('SELECT status, COUNT(*) AS cnt FROM reports GROUP BY status');

while ($statusRow = $statsResult->fetch_assoc()) {
    $statusCounts['total'] += (int) $statusRow['cnt'];

    if ($statusRow['status'] === STATUS_PENDING) {
        $statusCounts['pending'] = (int) $statusRow['cnt'];
    } elseif ($statusRow['status'] === STATUS_INVESTIGATING) {
        $statusCounts['investigating'] = (int) $statusRow['cnt'];
    } elseif ($statusRow['status'] === STATUS_RESOLVED) {
        $statusCounts['resolved'] = (int) $statusRow['cnt'];
    }
}

jsonResponse(true, 'Reports loaded.', [
    'reports' => $reportList,
    'stats' => $statusCounts,
]);
