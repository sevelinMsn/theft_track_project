<?php
/**
 * admin_users.php — List registered users for the admin panel
 *
 * Method: GET
 * Input: q (optional search)
 * Output: users[], total
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

$searchText = cleanInput($_GET['q'] ?? '', 100);

$userSelectSql = 'SELECT u.id, u.fullname, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id OR r.email = u.email) AS report_count
     FROM users u';

if ($searchText !== '') {
    $searchPattern = '%' . $searchText . '%';
    $statement = $conn->prepare(
        $userSelectSql . ' WHERE u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
         ORDER BY u.created_at DESC'
    );
    $statement->bind_param('sss', $searchPattern, $searchPattern, $searchPattern);
    $statement->execute();
    $result = $statement->get_result();
} else {
    $result = $conn->query($userSelectSql . ' ORDER BY u.created_at DESC');
}

$userList = [];
while ($userRow = $result->fetch_assoc()) {
    $userList[] = [
        'id' => (int) $userRow['id'],
        'fullname' => $userRow['fullname'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'],
        'created_at' => $userRow['created_at'],
        'report_count' => (int) $userRow['report_count'],
    ];
}

jsonResponse(true, 'Users loaded.', [
    'users' => $userList,
    'total' => count($userList),
]);
