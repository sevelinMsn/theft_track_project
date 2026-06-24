<?php
/**
 * seed_admin.php — Create the default admin account (run once)
 *
 * Visit in browser: http://localhost/theft_track_project/backend/seed_admin.php
 * Login: admin / admin123
 *
 * Delete this file on a real production server.
 */
require_once __DIR__ . '/db.php';

$adminUsername = 'admin';
$adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
$adminFullName = 'System Administrator';

$statement = $conn->prepare(
    'INSERT INTO admins (username, password, fullname) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE password = VALUES(password), fullname = VALUES(fullname)'
);
$statement->bind_param('sss', $adminUsername, $adminPasswordHash, $adminFullName);
$statement->execute();

echo 'Admin ready. Username: admin | Password: admin123';
