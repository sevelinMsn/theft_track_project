<?php
/**
 * user_dashboard.php — Dashboard data for logged-in user
 *
 * Method: GET
 * Output: profile, stats (pending/investigating/resolved), reports, notifications
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$loggedInUser = requireUserSession();

// --- User profile (member since date) ---
$profileStatement = $conn->prepare(
    'SELECT u.created_at AS member_since,
            (SELECT COUNT(*) FROM reports WHERE user_id = u.id OR email = u.email) AS total_reports
     FROM users u WHERE u.id = ? LIMIT 1'
);
$profileStatement->bind_param('i', $loggedInUser['id']);
$profileStatement->execute();
$profileRow = $profileStatement->get_result()->fetch_assoc();

// --- All reports for this user ---
$reportsStatement = $conn->prepare(
    'SELECT * FROM reports WHERE user_id = ? OR email = ? ORDER BY created_at DESC'
);
$reportsStatement->bind_param('is', $loggedInUser['id'], $loggedInUser['email']);
$reportsStatement->execute();
$reportsResult = $reportsStatement->get_result();

$reportList = [];
$statusCounts = ['total' => 0, 'pending' => 0, 'investigating' => 0, 'resolved' => 0];
$notificationList = [];

while ($reportRow = $reportsResult->fetch_assoc()) {
    $formattedReport = formatReportForApi($conn, $reportRow);
    $reportList[] = $formattedReport;

    $statusCounts['total']++;

    if ($formattedReport['status'] === 'pending') {
        $statusCounts['pending']++;
    } elseif ($formattedReport['status'] === 'investigating') {
        $statusCounts['investigating']++;
    } elseif ($formattedReport['status'] === 'resolved') {
        $statusCounts['resolved']++;
    }

    // Build notifications from recent timeline updates
    $recentUpdates = array_slice(array_reverse($formattedReport['updates']), 0, 3);
    foreach ($recentUpdates as $update) {
        if (!empty($update['date']) && $update['title'] !== 'Report Received') {
            $notificationList[] = [
                'tracking_id' => $formattedReport['tracking_id'],
                'itemName' => $formattedReport['itemName'],
                'title' => $update['title'],
                'message' => $update['message'],
                'date' => $update['date'],
            ];
        }
    }
}

// Newest notifications first, max 10
usort($notificationList, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
$notificationList = array_slice($notificationList, 0, 10);

jsonResponse(true, 'Dashboard loaded.', [
    'profile' => [
        'id' => $loggedInUser['id'],
        'fullname' => $loggedInUser['fullname'],
        'email' => $loggedInUser['email'],
        'phone' => $loggedInUser['phone'],
        'member_since' => $profileRow['member_since'] ?? null,
        'total_reports' => (int) ($profileRow['total_reports'] ?? $statusCounts['total']),
    ],
    'stats' => $statusCounts,
    'reports' => $reportList,
    'notifications' => $notificationList,
]);
