<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$data = getJsonInput();
$username = cleanInput($data['username'] ?? '', 50);
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    jsonResponse(false, 'Username and password are required.');
}

if (!verifyAdminLogin($conn, $username, $password)) {
    jsonResponse(false, 'Invalid admin credentials.', [], 401);
}

startAppSession();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_name'] = $username;

jsonResponse(true, 'Admin login successful.');
