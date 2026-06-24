<?php
/**
 * admin_user_reports.php — All reports filed by one user
 *
 * Method: GET
 * Input: user_id
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    jsonResponse(false, 'User ID is required.');
}

$userStatement = $conn->prepare(
    'SELECT id, fullname, email, phone, created_at FROM users WHERE id = ? LIMIT 1'
);
$userStatement->bind_param('i', $userId);
$userStatement->execute();
$userRow = $userStatement->get_result()->fetch_assoc();

if (!$userRow) {
    jsonResponse(false, 'User not found.', [], 404);
}

$reportsStatement = $conn->prepare(
    'SELECT * FROM reports WHERE user_id = ? OR email = ? ORDER BY created_at DESC'
);
$reportsStatement->bind_param('is', $userId, $userRow['email']);
$reportsStatement->execute();
$reportsResult = $reportsStatement->get_result();

$reportList = [];
while ($reportRow = $reportsResult->fetch_assoc()) {
    $reportList[] = formatReportForApi($conn, $reportRow, true);
}

jsonResponse(true, 'User reports loaded.', [
    'user' => [
        'id' => (int) $userRow['id'],
        'fullname' => $userRow['fullname'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'],
        'created_at' => $userRow['created_at'],
    ],
    'reports' => $reportList,
]);
