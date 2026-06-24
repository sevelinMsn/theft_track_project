<?php
/**
 * admin_overview.php — Dashboard statistics for the admin home tab
 *
 * Method: GET
 * Output: stats, recent_reports, recent_users, recent_activity
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

$overviewStats = [
    'reports' => ['total' => 0, 'pending' => 0, 'investigating' => 0, 'resolved' => 0],
    'users' => 0,
    'guest_reports' => 0,
    'registered_reports' => 0,
];

// Count reports by status
$statusResult = $conn->query('SELECT status, COUNT(*) AS cnt FROM reports GROUP BY status');
while ($statusRow = $statusResult->fetch_assoc()) {
    $overviewStats['reports']['total'] += (int) $statusRow['cnt'];

    if ($statusRow['status'] === STATUS_PENDING) {
        $overviewStats['reports']['pending'] = (int) $statusRow['cnt'];
    } elseif ($statusRow['status'] === STATUS_INVESTIGATING) {
        $overviewStats['reports']['investigating'] = (int) $statusRow['cnt'];
    } elseif ($statusRow['status'] === STATUS_RESOLVED) {
        $overviewStats['reports']['resolved'] = (int) $statusRow['cnt'];
    }
}

$countRow = $conn->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc();
$overviewStats['users'] = (int) $countRow['c'];

$countRow = $conn->query('SELECT COUNT(*) AS c FROM reports WHERE user_id IS NULL')->fetch_assoc();
$overviewStats['guest_reports'] = (int) $countRow['c'];

$overviewStats['registered_reports'] = $overviewStats['reports']['total'] - $overviewStats['guest_reports'];

$countRow = $conn->query(
    "SELECT COUNT(*) AS c FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch_assoc();
$overviewStats['reports_this_week'] = (int) ($countRow['c'] ?? 0);

$countRow = $conn->query(
    "SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetch_assoc();
$overviewStats['new_users_month'] = (int) ($countRow['c'] ?? 0);

// Last 5 reports
$recentReportList = [];
$reportsResult = $conn->query('SELECT * FROM reports ORDER BY created_at DESC LIMIT 5');
while ($reportRow = $reportsResult->fetch_assoc()) {
    $recentReportList[] = formatReportForApi($conn, $reportRow, true);
}

// Last 5 registered users
$recentUserList = [];
$usersResult = $conn->query(
    'SELECT u.id, u.fullname, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id OR r.email = u.email) AS report_count
     FROM users u ORDER BY u.created_at DESC LIMIT 5'
);
while ($userRow = $usersResult->fetch_assoc()) {
    $recentUserList[] = [
        'id' => (int) $userRow['id'],
        'fullname' => $userRow['fullname'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'],
        'created_at' => $userRow['created_at'],
        'report_count' => (int) $userRow['report_count'],
    ];
}

// Recent case updates (status changes and notes)
$recentActivityList = [];
try {
    $activityResult = $conn->query(
        'SELECT cu.update_type, cu.status_value, cu.note_text, cu.created_by, cu.created_at,
                r.tracking_id, r.fullname, r.item
         FROM case_updates cu
         INNER JOIN reports r ON r.id = cu.report_id
         ORDER BY cu.created_at DESC
         LIMIT 15'
    );
    while ($activityRow = $activityResult->fetch_assoc()) {
        $parsedItem = parseItemField($activityRow['item']);
        $recentActivityList[] = [
            'type' => $activityRow['update_type'],
            'tracking_id' => $activityRow['tracking_id'],
            'reporter' => $activityRow['fullname'],
            'itemName' => $parsedItem['itemName'],
            'status' => $activityRow['status_value'],
            'note' => $activityRow['note_text'],
            'by' => $activityRow['created_by'],
            'date' => $activityRow['created_at'],
        ];
    }
} catch (mysqli_sql_exception $e) {
    $recentActivityList = [];
}

jsonResponse(true, 'Overview loaded.', [
    'stats' => $overviewStats,
    'recent_reports' => $recentReportList,
    'recent_users' => $recentUserList,
    'recent_activity' => $recentActivityList,
]);
